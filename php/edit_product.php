<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/activity.php';
require_auth();

$productId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($productId <= 0) {
	http_response_code(400);
	echo 'Invalid product.';
	exit;
}

$stmt = $pdo->prepare(
	'SELECT id, name, description, shelf, created_at, created_by, updated_at, updated_by
	 FROM products WHERE id = ? AND deleted_at IS NULL LIMIT 1'
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
		$updates = [];
		$params = [];
		$changes = [];

		if ($canEditItem) {
			$name = trim($_POST['name'] ?? '');
			$description = trim($_POST['description'] ?? '');
			if ($name === '') {
				$error = 'Name is required.';
			} elseif (strlen($name) > 200) {
				$error = 'Name cannot exceed 200 characters.';
			} elseif (strlen($description) > 2000) {
				$error = 'Description cannot exceed 2000 characters.';
			} else {
				if ($name !== $product['name']) {
					$updates[] = 'name = ?';
					$params[] = $name;
					$changes[] = ['field' => 'name', 'old' => $product['name'], 'new' => $name];
				}
				$nextDescription = $description !== '' ? $description : null;
				if ($nextDescription !== $product['description']) {
					$updates[] = 'description = ?';
					$params[] = $nextDescription;
					$changes[] = ['field' => 'description', 'old' => $product['description'], 'new' => $nextDescription];
				}
			}
		}

		if ($canEditShelf) {
			$shelf = trim($_POST['shelf'] ?? '');
			if ($shelf === '') {
				$error = $error !== '' ? $error : 'Shelf is required.';
			} elseif (strlen($shelf) > 10) {
				$error = $error !== '' ? $error : 'Shelf cannot exceed 10 characters.';
			} elseif (!preg_match('/^[A-Z]+\d+$/', $shelf)) {
				$error = $error !== '' ? $error : 'Shelf must be in format like "A1" or "B2" (letters followed by numbers).';
			} else {
				if ($shelf !== $product['shelf']) {
					$updates[] = 'shelf = ?';
					$params[] = $shelf;
					$changes[] = ['field' => 'shelf', 'old' => $product['shelf'], 'new' => $shelf];
				}
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
			foreach ($changes as $change) {
				log_activity(
					$pdo,
					$_SESSION['user_id'] ?? null,
					'update',
					'product',
					$productId,
					$change['field'],
					$change['old'] === null ? null : (string) $change['old'],
					$change['new'] === null ? null : (string) $change['new']
				);
			}
			header('Location: products.php?updated=1');
			exit;
		}
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
				<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
				<input type="hidden" name="id" value="<?php echo (int) $productId; ?>">
				<label for="name">Name</label>
			<input type="text" id="name" name="name" value="<?php echo htmlspecialchars($displayName); ?>" maxlength="200" <?php echo $canEditItem ? '' : 'disabled'; ?> required>
			<label for="description">Description</label>
			<textarea id="description" name="description" maxlength="2000" <?php echo $canEditItem ? '' : 'disabled'; ?>><?php echo htmlspecialchars($displayDescription ?? ''); ?></textarea>
			<label for="shelf">Shelf</label>
			<input type="text" id="shelf" name="shelf" value="<?php echo htmlspecialchars($displayShelf); ?>" pattern="[A-Z]+[0-9]+" maxlength="10" <?php echo $canEditShelf ? '' : 'disabled'; ?> required>
				<label>Created at</label>
				<input type="text" value="<?php echo htmlspecialchars($createdAt); ?>" disabled>
				<button type="submit">Save</button>
			</form>
		</div>
	</div>

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
