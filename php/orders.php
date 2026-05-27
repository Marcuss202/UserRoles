<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/activity.php';
require_auth();

if (!can_view_orders()) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$canManage = can_manage_orders();
$error = '';
$success = '';

function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

$csrfToken = generateCSRFToken();

function email_local_part(?string $email): string {
    if (!$email) {
        return '-';
    }
    $parts = explode('@', $email, 2);
    return $parts[0] !== '' ? $parts[0] : $email;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        if (!$canManage) {
            http_response_code(403);
            $error = 'Access denied.';
        } else {
            $productId = (int) ($_POST['product_id'] ?? 0);
            $quantity = (int) ($_POST['quantity'] ?? 0);
            $createdBy = $_SESSION['user_id'] ?? null;

            if ($createdBy === null) {
                $error = 'User session missing.';
            } elseif ($productId <= 0 || $quantity <= 0) {
                $error = 'Product and quantity are required.';
            } elseif ($quantity > 9999) {
                $error = 'Quantity cannot exceed 9999.';
            } else {
                // Verify product exists and is not deleted
                $productCheck = $pdo->prepare('SELECT id FROM products WHERE id = ? AND deleted_at IS NULL LIMIT 1');
                $productCheck->execute([$productId]);
                if (!$productCheck->fetch()) {
                    $error = 'Product not found or has been deleted.';
                } else {
                    $pdo->beginTransaction();
                    try {
                        $stmt = $pdo->prepare('INSERT INTO orders (created_by) VALUES (?)');
                        $stmt->execute([$createdBy]);
                        $orderId = (int) $pdo->lastInsertId();

                    $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)');
                    $itemStmt->execute([$orderId, $productId, $quantity]);

                    $pdo->commit();

                    $details = 'product_id=' . $productId . '; quantity=' . $quantity;
                    log_activity($pdo, $createdBy, 'create', 'order', $orderId, null, null, $details);
                    $success = 'Order created.';
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $error = 'Failed to create order.';
                }
                }
            }
        }
    } elseif ($action === 'accept' || $action === 'fulfill' || $action === 'update_status') {
        if (!$canManage) {
            http_response_code(403);
            $error = 'Access denied.';
        } else {
            $orderId = (int) ($_POST['order_id'] ?? 0);
            $userId = $_SESSION['user_id'] ?? null;

            if ($orderId <= 0 || $userId === null) {
                $error = 'Invalid request.';
            } else {
                $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ? LIMIT 1');
                $stmt->execute([$orderId]);
                $order = $stmt->fetch();

                if (!$order) {
                    $error = 'Order not found.';
                } else {
                    $currentStatus = $order['status'];
                    if ($action === 'accept' && $currentStatus === 'created') {
                        $update = $pdo->prepare(
                            'UPDATE orders SET status = ?, accepted_by = ?, accepted_at = NOW() WHERE id = ?'
                        );
                        $update->execute(['accepted', $userId, $orderId]);
                        log_activity($pdo, $userId, 'status', 'order', $orderId, 'status', 'created', 'accepted');
                        $success = 'Order accepted.';
                    } elseif ($action === 'fulfill' && $currentStatus === 'accepted') {
                        $update = $pdo->prepare(
                            'UPDATE orders SET status = ?, fulfilled_by = ?, fulfilled_at = NOW() WHERE id = ?'
                        );
                        $update->execute(['fulfilled', $userId, $orderId]);
                        log_activity($pdo, $userId, 'status', 'order', $orderId, 'status', 'accepted', 'fulfilled');
                        $success = 'Order fulfilled.';
                    } elseif ($action === 'update_status') {
                        $newStatus = $_POST['status'] ?? '';
                        if (!in_array($newStatus, ['created', 'accepted', 'fulfilled'], true)) {
                            $error = 'Invalid status.';
                        } elseif ($newStatus === $currentStatus) {
                            $error = 'Status unchanged.';
                        } else {
                            if ($newStatus === 'created') {
                                $update = $pdo->prepare(
                                    'UPDATE orders SET status = ?, accepted_by = NULL, accepted_at = NULL, fulfilled_by = NULL, fulfilled_at = NULL WHERE id = ?'
                                );
                                $update->execute(['created', $orderId]);
                            } elseif ($newStatus === 'accepted') {
                                $update = $pdo->prepare(
                                    'UPDATE orders SET status = ?, accepted_by = ?, accepted_at = NOW(), fulfilled_by = NULL, fulfilled_at = NULL WHERE id = ?'
                                );
                                $update->execute(['accepted', $userId, $orderId]);
                            } else {
                                $update = $pdo->prepare(
                                    'UPDATE orders SET status = ?, accepted_by = COALESCE(accepted_by, ?), accepted_at = COALESCE(accepted_at, NOW()), fulfilled_by = ?, fulfilled_at = NOW() WHERE id = ?'
                                );
                                $update->execute(['fulfilled', $userId, $userId, $orderId]);
                            }
                            log_activity($pdo, $userId, 'status', 'order', $orderId, 'status', $currentStatus, $newStatus);
                            $success = 'Order status updated.';
                        }
                    } else {
                        $error = 'Invalid status transition.';
                    }
                }
            }
        }
    }
    }
}

$productsStmt = $pdo->query('SELECT id, name FROM products WHERE deleted_at IS NULL ORDER BY name ASC');
$products = $productsStmt->fetchAll();

$orderStmt = $pdo->query(
    'SELECT o.id,
            o.status,
            o.created_at,
            o.accepted_at,
            o.fulfilled_at,
            u_create.email AS created_by_email,
            u_accept.email AS accepted_by_email,
            u_fulfill.email AS fulfilled_by_email,
            oi.product_id,
            oi.quantity,
            p.name AS product_name
     FROM orders o
     LEFT JOIN users u_create ON u_create.id = o.created_by
     LEFT JOIN users u_accept ON u_accept.id = o.accepted_by
     LEFT JOIN users u_fulfill ON u_fulfill.id = o.fulfilled_by
     LEFT JOIN order_items oi ON oi.order_id = o.id
     LEFT JOIN products p ON p.id = oi.product_id
     ORDER BY o.created_at DESC, oi.id ASC'
);
$rows = $orderStmt->fetchAll();

$orders = [];
foreach ($rows as $row) {
    $orderId = (int) $row['id'];
    if (!isset($orders[$orderId])) {
        $orders[$orderId] = [
            'id' => $orderId,
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'accepted_at' => $row['accepted_at'],
            'fulfilled_at' => $row['fulfilled_at'],
            'created_by_email' => $row['created_by_email'],
            'accepted_by_email' => $row['accepted_by_email'],
            'fulfilled_by_email' => $row['fulfilled_by_email'],
            'items' => [],
        ];
    }
    if ($row['product_id']) {
        $orders[$orderId]['items'][] = [
            'product_name' => $row['product_name'],
            'quantity' => (int) $row['quantity'],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order management</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="page-wide">
    <div class="container-wide">
        <div class="panel-flat">
            <div class="topbar">
                <h1>Order management</h1>
                <div class="topbar-actions">
                    <a class="link-btn btn-red" href="dashboard.php">Back</a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($canManage): ?>
                <form method="post" action="orders.php" class="order-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="create">
                    <div>
                        <label for="product_id">Product</label>
                        <select id="product_id" name="product_id" required>
                            <option value="">Select product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo (int) $product['id']; ?>">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="quantity">Quantity</label>
                        <input type="number" id="quantity" name="quantity" min="1" max="9999" value="1" required>
                    </div>
                    <div>
                        <button type="submit">Create order</button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if (!$orders): ?>
                <div class="table-empty">No orders found.</div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th>Created</th>
                            <th>Created By</th>
                            <th>Accepted</th>
                            <th>Accepted By</th>
                            <th>Fulfilled</th>
                            <th>Fulfilled By</th>
                            <?php if ($canManage): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo (int) $order['id']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                                        <?php echo htmlspecialchars($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!$order['items']): ?>
                                        -
                                    <?php else: ?>
                                        <?php foreach ($order['items'] as $item): ?>
                                            <?php echo htmlspecialchars($item['product_name']); ?> x<?php echo (int) $item['quantity']; ?><br>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($order['created_at']); ?></td>
                                <td><?php echo htmlspecialchars(email_local_part($order['created_by_email'] ?? null)); ?></td>
                                <td><?php echo htmlspecialchars($order['accepted_at'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars(email_local_part($order['accepted_by_email'] ?? null)); ?></td>
                                <td><?php echo htmlspecialchars($order['fulfilled_at'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars(email_local_part($order['fulfilled_by_email'] ?? null)); ?></td>
                                <?php if ($canManage): ?>
                                    <td>
                                        <form method="post" action="orders.php" class="inline-form">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                            <select name="status">
                                                <option value="created" <?php echo $order['status'] === 'created' ? 'selected' : ''; ?>>Created</option>
                                                <option value="accepted" <?php echo $order['status'] === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                                <option value="fulfilled" <?php echo $order['status'] === 'fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                                            </select>
                                            <button type="submit" class="link-btn btn-gray">Save</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
