<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_auth();

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
    <div class="container-wide">
        <div class="panel-flat">
            <div class="topbar">
                <h1>Products</h1>
                <div class="topbar-actions">
                    <a class="link-btn btn-green" href="add_product.php">Add product</a>
                    <a class="link-btn btn-red" href="dashboard.php">Back</a>
                </div>
            </div>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo (int) $product['id']; ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['description'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($product['shelf']); ?></td>
                                <td><?php echo htmlspecialchars($product['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($product['created_by_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($product['updated_at'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($product['updated_by_name'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
