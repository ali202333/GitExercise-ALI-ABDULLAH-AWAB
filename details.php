<?php
session_start();

$isLoggedIn = isset($_SESSION['user_id']);

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

$movieId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, name, rating, genres, movie_url FROM titles WHERE id = :id');
$stmt->execute(['id' => $movieId]);
$movie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$movie) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo $movie ? htmlspecialchars($movie['name']) : 'Not found'; ?> - S-CoreBox</title>
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
			padding: 24px;
			box-sizing: border-box;
		}

		.movie-details-template {
			width: min(100%, 520px);
			box-sizing: border-box;
			padding: 28px;
			border: 1px solid #cfcfcf;
			border-radius: 6px;
		}

		.movie-details-template h2 {
			margin: 0 0 20px;
		}

		.movie-details-template p {
			margin: 0 0 16px;
		}

		.movie-details-template a {
			color: #222;
			word-break: break-all;
		}

		.movie-details-genres {
			color: #555;
		}

		.close-details-button {
			display: block;
			width: 100%;
			box-sizing: border-box;
			padding: 12px 20px;
			border: 1px solid #222;
			border-radius: 6px;
			background: #222;
			color: #ffffff;
			font: inherit;
			text-align: center;
			text-decoration: none;
			cursor: pointer;
		}

		.close-details-button:hover {
			background: #444;
		}
	</style>
</head>
<body>
	<section class="movie-details-template">
		<?php if ($movie): ?>
			<h2><?php echo htmlspecialchars($movie['name']); ?></h2>
			<p><strong>Rating:</strong> &#9733; <?php echo htmlspecialchars($movie['rating'] ?? 'N/A'); ?></p>
			<p><strong>IMDb:</strong>
				<?php if ($movie['movie_url']): ?>
					<a href="<?php echo htmlspecialchars($movie['movie_url']); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo htmlspecialchars($movie['movie_url']); ?>
					</a>
				<?php else: ?>
					Not available
				<?php endif; ?>
			</p>
			<p><strong>Genres:</strong> <span class="movie-details-genres"><?php echo htmlspecialchars($movie['genres'] ?? 'Unknown'); ?></span></p>
		<?php else: ?>
			<h2>Movie not found</h2>
			<p>This title doesn't exist in the database.</p>
		<?php endif; ?>

		<a class="close-details-button" href="index.php">Back to list</a>
	</section>
</body>
</html>
