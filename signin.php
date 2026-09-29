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
$justRegistered = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username'] ?? '');
    $password        = $_POST['password'] ?? '';

    if ($usernameOrEmail === '' || $password === '') {
        $errors[] = 'Please enter your username/email and password.';
    } else {
        // Allow logging in with either username or email
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :u OR email = :u');
        $stmt->execute(['u' => $usernameOrEmail]);
        $foundUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($foundUser && password_verify($password, $foundUser['password_hash'])) {
            // Correct password -> log them in
            $_SESSION['user_id']  = $foundUser['id'];
            $_SESSION['username'] = $foundUser['username'];
            header('Location: index.php');
            exit;
        } else {
            // Same error for "no such user" and "wrong password" on purpose -
            // this stops someone from guessing which usernames exist.
            $errors[] = 'Incorrect username/email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Sign In - S-CoreBox</title>
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

		.success {
			background: #eafaf1;
			border: 1px solid #b7e4c7;
			color: #14532d;
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
		<h1>Sign in</h1>

		<?php if ($justRegistered): ?>
			<div class="success">Account created! You can sign in now.</div>
		<?php endif; ?>

		<?php if (!empty($errors)): ?>
			<div class="errors">
				<?php foreach ($errors as $error): ?>
					<div><?php echo htmlspecialchars($error); ?></div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<form method="post" action="signin.php">
			<label for="username">Username or Email</label>
			<input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>

			<label for="password">Password</label>
			<input type="password" id="password" name="password" required>

			<button type="submit">Sign In</button>
		</form>

		<div class="switch-link">
			Don't have an account? <a href="signup.php">Sign up</a>
		</div>
	</div>
</body>
</html>
