<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/activity.php';
require_auth();

if (!can_add_product()) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

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
$csrfToken = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $shelf = trim($_POST['shelf'] ?? '');
        $createdBy = $_SESSION['user_id'] ?? null;

        if ($createdBy === null) {
            $error = 'User session missing.';
        } elseif ($name === '' || $shelf === '') {
            $error = 'Name and shelf are required.';
        } elseif (strlen($name) > 200) {
            $error = 'Name cannot exceed 200 characters.';
        } elseif (strlen($shelf) > 10) {
            $error = 'Shelf cannot exceed 10 characters.';
        } elseif (!preg_match('/^[A-Z]+\d+$/', $shelf)) {
            $error = 'Shelf must be in format like "A1" or "B2" (letters followed by numbers).';
        } elseif (strlen($description) > 2000) {
            $error = 'Description cannot exceed 2000 characters.'
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
            $productId = (int) $pdo->lastInsertId();
            $details = 'name=' . $name . '; shelf=' . $shelf . '; description=' . ($description !== '' ? $description : '-');
            log_activity($pdo, $createdBy, 'create', 'product', $productId, null, null, $details);
            header('Location: products.php?added=1');
            exit;
        }
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
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" maxlength="200" required>
                <label for="description">Description</label>
                <textarea id="description" name="description" maxlength="2000"></textarea>
                <label for="shelf">Shelf</label>
                <input type="text" id="shelf" name="shelf" placeholder="A1" pattern="[A-Z]+[0-9]+" maxlength="10" required>
                <button type="submit">Add</button>
            </form>
        </div>
    </div>
</body>
</html>
