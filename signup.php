<?php
session_start();

$host = 'localhost';
$db   = 'Scorebox';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$errors = [];

// ---- This block only runs when the form has actually been submitted ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // ---- Basic server-side validation ----
    if ($username === '' || $email === '' || $password === '') {
        $errors[] = 'All fields are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // ---- Check if username or email is already taken ----
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email');
        $stmt->execute(['username' => $username, 'email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'That username or email is already registered.';
        }
    }

    // ---- If everything checks out, create the account ----
    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)'
        );
        $stmt->execute([
            'username'      => $username,
            'email'         => $email,
            'password_hash' => $passwordHash,
        ]);

        // Redirect to sign in with a success message
        header('Location: signin.php?registered=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Sign Up - S-CoreBox</title>
	<style>
		body {
			margin: 0;
			background: #ffffff;
			color: #222;
			font-family: Arial, sans-serif;
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
		}

		.auth-box {
			width: 100%;
			max-width: 360px;
			padding: 32px;
			border: 1px solid #e5e5e5;
			border-radius: 8px;
		}

		h1 {
			font-size: 1.5rem;
			margin-bottom: 24px;
			text-align: center;
		}

		label {
			display: block;
			font-size: 0.85rem;
			margin-bottom: 4px;
			margin-top: 16px;
		}

		input {
			width: 100%;
			padding: 10px 12px;
			box-sizing: border-box;
			border: 1px solid #cfcfcf;
			border-radius: 6px;
			font: inherit;
		}

		button {
			width: 100%;
			margin-top: 24px;
			padding: 12px;
			border: 1px solid #222;
			border-radius: 6px;
			background: #222;
			color: #ffffff;
			font: inherit;
			cursor: pointer;
		}

		.errors {
			background: #fdecea;
			border: 1px solid #f5c6cb;
			color: #611a15;
			padding: 12px;
			border-radius: 6px;
			margin-top: 16px;
			font-size: 0.85rem;
		}

		.switch-link {
			text-align: center;
			margin-top: 16px;
			font-size: 0.85rem;
		}
	</style>
</head>
<body>
	<div class="auth-box">
		<h1>Create an account</h1>

		<?php if (!empty($errors)): ?>
			<div class="errors">
				<?php foreach ($errors as $error): ?>
					<div><?php echo htmlspecialchars($error); ?></div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<form method="post" action="signup.php">
			<label for="username">Username</label>
			<input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>

			<label for="email">Email</label>
			<input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>

			<label for="password">Password</label>
			<input type="password" id="password" name="password" required>

			<label for="confirm_password">Confirm Password</label>
			<input type="password" id="confirm_password" name="confirm_password" required>

			<button type="submit">Sign Up</button>
		</form>

		<div class="switch-link">
			Already have an account? <a href="signin.php">Sign in</a>
		</div>
	</div>
</body>
</html>
