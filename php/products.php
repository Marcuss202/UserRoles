<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_auth();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $shelf = trim($_POST['shelf'] ?? '');
    $createdBy = $_SESSION['user_id'] ?? null;

    if ($createdBy === null) {
        $error = 'User session missing.';
    } elseif ($name === '' || $shelf === '') {
        $error = 'Name and shelf are required.';
    } else {
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
                    <button class="link-btn btn-green" type="button" id="openAddProduct">Add product</button>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal-backdrop" id="addProductModal" aria-hidden="true">
        <div class="modal-card modal-wrap" role="dialog" aria-modal="true" aria-labelledby="addProductTitle">
            <h2 class="modal-title" id="addProductTitle">Add product</h2>
            <p class="modal-subtitle">Fill in the details below.</p>
            <form method="post" action="products.php">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required>
                <label for="description">Description</label>
                <textarea id="description" name="description"></textarea>
                <label for="shelf">Shelf</label>
                <input type="text" id="shelf" name="shelf" placeholder="A1" required>
                <div class="buttons_add">
                    <button type="submit">Add</button>
                    <button class="modal-close close_btn" type="button" id="closeAddProduct" aria-label="Close">&times;</button>
                </div>
            </form>
        </div>
    </div>

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
</body>
</html>
