<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_auth();

$error = '';

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
        header('Location: products.php?added=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add product</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="topbar">
                <h1>Add product</h1>
                <a class="link-btn btn-red" href="products.php">Back</a>
            </div>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" action="add_product.php">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required>
                <label for="description">Description</label>
                <textarea id="description" name="description"></textarea>
                <label for="shelf">Shelf</label>
                <input type="text" id="shelf" name="shelf" placeholder="A1" required>
                <button type="submit">Add</button>
            </form>
        </div>
    </div>
</body>
</html>
