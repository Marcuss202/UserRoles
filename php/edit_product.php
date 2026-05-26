<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_auth();

$productId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($productId <= 0) {
	http_response_code(400);
	echo 'Invalid product.';
	exit;
}

$stmt = $pdo->prepare(
	'SELECT id, name, description, shelf, created_at, created_by, updated_at, updated_by
	 FROM products WHERE id = ? LIMIT 1'
);
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
	http_response_code(404);
	echo 'Product not found.';
	exit;
}

$canEditItem = can_edit_item_fields();
$canEditShelf = can_edit_shelf();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$updates = [];
	$params = [];

	if ($canEditItem) {
		$name = trim($_POST['name'] ?? '');
		$description = trim($_POST['description'] ?? '');
		if ($name === '') {
			$error = 'Name is required.';
		} else {
			$updates[] = 'name = ?';
			$params[] = $name;
			$updates[] = 'description = ?';
			$params[] = $description !== '' ? $description : null;
		}
	}

	if ($canEditShelf) {
		$shelf = trim($_POST['shelf'] ?? '');
		if ($shelf === '') {
			$error = $error !== '' ? $error : 'Shelf is required.';
		} else {
			$updates[] = 'shelf = ?';
			$params[] = $shelf;
		}
	}

	if ($error === '' && !$updates) {
		$error = 'Nothing to update.';
	}

	if ($error === '') {
		$updates[] = 'updated_by = ?';
		$params[] = $_SESSION['user_id'] ?? null;
		$params[] = $productId;

		$sql = 'UPDATE products SET ' . implode(', ', $updates) . ' WHERE id = ?';
		$updateStmt = $pdo->prepare($sql);
		$updateStmt->execute($params);
		header('Location: products.php?updated=1');
		exit;
	}
}

$displayName = $product['name'] ?? '';
$displayDescription = $product['description'] ?? '';
$displayShelf = $product['shelf'] ?? '';
$createdAt = $product['created_at'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Edit product</title>
	<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
	<div class="container">
		<div class="card">
			<div class="topbar">
				<h1>Edit product</h1>
				<a class="link-btn btn-red" href="products.php">Back</a>
			</div>
			<?php if ($error): ?>
				<div class="error"><?php echo htmlspecialchars($error); ?></div>
			<?php endif; ?>
			<form method="post" action="edit_product.php">
				<input type="hidden" name="id" value="<?php echo (int) $productId; ?>">
				<label for="name">Name</label>
				<input type="text" id="name" name="name" value="<?php echo htmlspecialchars($displayName); ?>" <?php echo $canEditItem ? '' : 'disabled'; ?> required>
				<label for="description">Description</label>
				<textarea id="description" name="description" <?php echo $canEditItem ? '' : 'disabled'; ?>><?php echo htmlspecialchars($displayDescription ?? ''); ?></textarea>
				<label for="shelf">Shelf</label>
				<input type="text" id="shelf" name="shelf" value="<?php echo htmlspecialchars($displayShelf); ?>" <?php echo $canEditShelf ? '' : 'disabled'; ?> required>
				<label>Created at</label>
				<input type="text" value="<?php echo htmlspecialchars($createdAt); ?>" disabled>
				<button type="submit">Save</button>
			</form>
		</div>
	</div>
</body>
</html>
