<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/activity.php';
require_auth_with_user($pdo);

$canAdd = can_add_product();
$canEdit = can_edit_item_fields() || can_edit_shelf();
$canDelete = can_edit_item_fields();

function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

$error = '';
$success = '';
$csrfToken = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } elseif (!$canDelete) {
        http_response_code(403);
        $error = 'Access denied.';
    } else {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $userId = $_SESSION['user_id'] ?? null;
        
        if ($productId <= 0 || $userId === null) {
            $error = 'Invalid request.';
        } else {
            try {
                $deleteStmt = $pdo->prepare(
                    'UPDATE products SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND deleted_at IS NULL'
                );
                $deleteStmt->execute([$userId, $productId]);
                
                if ($deleteStmt->rowCount() > 0) {
                    log_activity($pdo, $userId, 'delete', 'product', $productId, null, null, 'Product soft-deleted');
                    $success = 'Product deleted successfully.';
                } else {
                    $error = 'Product not found or already deleted.';
                }
            } catch (PDOException $e) {
                error_log('Product deletion error: ' . $e->getMessage());
                $error = 'Failed to delete product.';
            }
        }
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } elseif (!$canAdd) {
        http_response_code(403);
        $error = 'Access denied.';
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $shelf = trim($_POST['shelf'] ?? '');
    $createdBy = $_SESSION['user_id'] ?? null;

    if ($error === '' && $createdBy === null) {
        $error = 'User session missing.';
    } elseif ($error === '' && ($name === '' || $shelf === '')) {
        $error = 'Name and shelf are required.';
    } elseif ($error === '' && strlen($name) > 50) {
        $error = 'Name cannot exceed 50 characters.';
    } elseif ($error === '') {
        $stmt = $pdo->prepare(
            'INSERT INTO products (name, description, shelf, created_by)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $name,
            $description !== '' ? $description : null,
            $shelf,
            $createdBy,
        ]);
        $productId = (int) $pdo->lastInsertId();
        $details = 'name=' . $name . '; shelf=' . $shelf . '; description=' . ($description !== '' ? $description : '-');
        log_activity($pdo, $createdBy, 'create', 'product', $productId, null, null, $details);
        $success = 'Product added successfully!';
    }
}

$stmt = $pdo->query(
    'SELECT p.id,
            p.name,
            p.description,
            p.shelf,
            p.created_at,
            p.updated_at,
            u_create.email AS created_by_name,
            u_update.email AS updated_by_name
     FROM products p
     LEFT JOIN users u_create ON u_create.id = p.created_by
     LEFT JOIN users u_update ON u_update.id = p.updated_by
     WHERE p.deleted_at IS NULL
     ORDER BY p.id DESC'
);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="page-wide">
    <div class="container-wide page-content" id="pageContent">
        <div class="panel-flat">
            <div class="topbar">
                <h1>Products</h1>
                <div class="topbar-actions">
                    <?php if ($canAdd): ?>
                        <button class="link-btn btn-green" type="button" id="openAddProduct">Add product</button>
                    <?php endif; ?>
                    <a class="link-btn btn-gray" href="report.php">Report</a>
                    <a class="link-btn btn-red" href="dashboard.php">Back</a>
                </div>
            </div>
            <?php if ($error): ?>
                <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (($_GET['added'] ?? '') === '1'): ?>
                <div class="notice">Product added.</div>
            <?php endif; ?>
            <?php if (!$products): ?>
                <div class="table-empty">No products found.</div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Shelf</th>
                            <th>Created At</th>
                            <th>Created By</th>
                            <th>Updated At</th>
                            <th>Updated By</th>
                            <?php if ($canEdit || $canDelete): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo (int) $product['id']; ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo mb_strimwidth(htmlspecialchars($product['description'] ?? ''), 0, 50, '...'); ?></td>
                                <td><?php echo htmlspecialchars($product['shelf']); ?></td>
                                <td><?php echo htmlspecialchars($product['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($product['created_by_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($product['updated_at'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($product['updated_by_name'] ?? '-'); ?></td>
                                <?php if ($canEdit || $canDelete): ?>
                                    <td>
                                        <div class="action-row">
                                            <?php if ($canEdit): ?>
                                                <a class="link-btn btn-gray" href="edit_product.php?id=<?php echo (int) $product['id']; ?>">Edit</a>
                                            <?php endif; ?>
                                            <?php if ($canDelete): ?>
                                                <form method="post" action="products.php" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                                    <button type="submit" class="link-btn btn-red">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/session-check.js"></script>
    <?php if ($canAdd): ?>
        <div class="modal-backdrop" id="addProductModal" aria-hidden="true">
            <div class="modal-card modal-wrap" role="dialog" aria-modal="true" aria-labelledby="addProductTitle">
                <h2 class="modal-title" id="addProductTitle">Add product</h2>
                <p class="modal-subtitle">Fill in the details below.</p>
                <form method="post" action="products.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" maxlength="50" required>
                    <label for="description">Description</label>
                    <textarea id="description" name="description" maxlength="2000"></textarea>
                    <label for="shelf">Shelf</label>
                    <input type="text" id="shelf" name="shelf" placeholder="A1" pattern="[A-Z]+[0-9]+" maxlength="10" required>
                    <div class="buttons_add">
                        <button type="submit">Add</button>
                        <button class="modal-close close_btn" type="button" id="closeAddProduct" aria-label="Close">&times;</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($canAdd): ?>
        <script>
            const openBtn = document.getElementById('openAddProduct');
            const closeBtn = document.getElementById('closeAddProduct');
            const modal = document.getElementById('addProductModal');
            const pageContent = document.getElementById('pageContent');

            function openModal() {
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                pageContent.classList.add('is-blurred');
                const nameInput = modal.querySelector('#name');
                if (nameInput) {
                    nameInput.focus();
                }
            }

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                pageContent.classList.remove('is-blurred');
            }

            openBtn.addEventListener('click', openModal);
            closeBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });
        </script>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert, .error, .notice');
            alerts.forEach(alert => {
                const isTempPasswordAlert = alert.textContent.includes('Temporary password');
                const dismissTime = isTempPasswordAlert ? 60000 : 3000;
                
                setTimeout(() => {
                    alert.classList.add('dismiss');
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, dismissTime);
            });
        });
    </script>
</body>
</html>
