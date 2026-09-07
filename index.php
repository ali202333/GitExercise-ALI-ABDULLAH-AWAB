<?php
session_start();
$isLoggedIn = isset($_SESSION['user_id']);

// ---- 1. Connect to the database ----
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

// ---- 2. Handle a bookmark toggle, if one was clicked ----
// This runs BEFORE we build the page, so the bookmark state is
// already updated by the time we display the list below.
if (isset($_GET['toggle_bookmark'])) {
    $movieId = (int) $_GET['toggle_bookmark']; // (int) forces it to be a number, for safety

    if (!isset($_SESSION['bookmarks'])) {
        $_SESSION['bookmarks'] = [];
    }

    if (in_array($movieId, $_SESSION['bookmarks'])) {
        // already bookmarked -> remove it
        $_SESSION['bookmarks'] = array_diff($_SESSION['bookmarks'], [$movieId]);
    } else {
        // not bookmarked yet -> add it
        $_SESSION['bookmarks'][] = $movieId;
    }

    // Redirect back to the same page MINUS the toggle_bookmark parameter,
    // so refreshing the page doesn't re-toggle it accidentally.
    $params = $_GET;
    unset($params['toggle_bookmark']);
    header('Location: index.php?' . http_build_query($params));
    exit;
}

// ---- 3. Read search/sort/filter choices from the URL ----
$search        = trim($_GET['search'] ?? '');
$sort          = $_GET['sort'] ?? 'none';       // 'asc', 'desc', or 'none'
$bookmarksOnly = isset($_GET['bookmarks_only']);

// ---- 4. Build the SQL query based on those choices ----
$sql    = 'SELECT id, name, rating, genres, movie_url FROM titles WHERE 1=1';
$params = [];

if ($search !== '') {
    $sql .= ' AND name LIKE :search';
    $params['search'] = '%' . $search . '%';
}

if ($bookmarksOnly) {
    $bookmarked = $_SESSION['bookmarks'] ?? [];
    if (empty($bookmarked)) {
        $sql .= ' AND 1=0'; // no bookmarks yet -> show nothing
    } else {
        // Build a list of :id0, :id1, :id2... placeholders, one per bookmarked movie
        $placeholders = [];
        foreach ($bookmarked as $index => $id) {
            $key = "bm$index";
            $placeholders[] = ":$key";
            $params[$key] = $id;
        }
        $sql .= ' AND id IN (' . implode(',', $placeholders) . ')';
    }
}

if ($sort === 'asc') {
    $sql .= ' ORDER BY name ASC';
} elseif ($sort === 'desc') {
    $sql .= ' ORDER BY name DESC';
} else {
    $sql .= ' ORDER BY id ASC';
}

// ---- 5. Run the query safely using a prepared statement ----
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Small helper to build a link that keeps existing filters ----
function buildLink($overrides) {
    $params = array_merge($_GET, $overrides);
    return 'index.php?' . http_build_query($params);
}

$currentBookmarks = $_SESSION['bookmarks'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>S-CoreBox</title>
	<style>
		body {
			margin: 0;
			background: #ffffff;
			color: #222;
			font-family: Arial, sans-serif;
		}

		.brand {
			position: absolute;
			top: 20px;
			right: 24px;
			font-size: 1.25rem;
			font-weight: bold;
		}

		.account-actions {
			position: absolute;
			top: 58px;
			right: 24px;
			display: flex;
			gap: 8px;
		}

		.sign-in-button,
		.sign-up-button {
			padding: 10px 18px;
			border: 1px solid #cfcfcf;
			border-radius: 6px;
			background: #ffffff;
			color: #222;
			font: inherit;
			cursor: pointer;
			text-decoration: none;
			display: inline-block;
		}

		header {
			padding: 80px 24px 24px;
			border-bottom: 1px solid #e5e5e5;
		}

		main {
			width: 75%;
			min-height: calc(100vh - 151px);
			padding: 32px 24px;
			box-sizing: border-box;
		}

		.main-actions {
			display: grid;
			grid-template-columns: repeat(3, minmax(0, 1fr));
			gap: 12px;
			margin-bottom: 32px;
		}

		.main-actions a.button,
		.main-actions button,
		.search-bar {
			padding: 12px 20px;
			box-sizing: border-box;
			border: 1px solid #cfcfcf;
			border-radius: 6px;
			background: #ffffff;
			color: #222;
			font: inherit;
			cursor: pointer;
			text-decoration: none;
			display: block;
			text-align: center;
		}

		.search-bar {
			grid-column: 1 / -1;
			width: 100%;
			box-sizing: border-box;
		}

		.main-actions a.active {
			border-color: #222;
			background: #222;
			color: #ffffff;
		}

		.mock-data-box {
			max-height: 360px;
			overflow-y: auto;
			border: 1px solid #cfcfcf;
			border-radius: 6px;
		}

		.movie-row {
			display: flex;
			align-items: stretch;
			border-bottom: 1px solid #e5e5e5;
		}

		.movie-info {
			flex: 1;
			padding: 16px 20px;
		}

		.movie-info strong {
			display: block;
		}

		.movie-info span {
			font-size: 0.85rem;
			color: #666;
		}

		.bookmark-button {
			width: 110px;
			padding: 12px;
			border: 0;
			border-left: 1px solid #e5e5e5;
			background: #ffffff;
			color: #222;
			font: inherit;
			cursor: pointer;
		}

		.bookmark-button.active {
			background: #dedede;
			font-weight: bold;
		}

		form {
			display: contents;
		}
	</style>
</head>
<body>
	<div class="brand">S-CoreBox</div>
	<nav class="account-actions" aria-label="Account actions"> <?php if ($isLoggedIn): ?> <span>Hi, <?php echo htmlspecialchars($_SESSION['username']); ?></span> <a class="sign-in-button" href="logout.php">Sign Out</a> <?php else: ?> <a class="sign-in-button" href="signin.php">Sign In</a> <a class="sign-up-button" href="signup.php">Sign Up</a> <?php endif; ?> </nav>

	<header>
		<h1>S-CoreBox</h1>
	</header>

	<main>
		<div class="main-actions" role="group" aria-label="Content controls">
			<!-- Search box: submitting this form reloads the page with ?search=... in the URL -->
			<form method="get" action="index.php" style="display:contents;">
				<?php if ($bookmarksOnly): ?>
					<input type="hidden" name="bookmarks_only" value="1">
				<?php endif; ?>
				<?php if ($sort !== 'none'): ?>
					<input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
				<?php endif; ?>
				<input class="search-bar" type="search" name="search"
					value="<?php echo htmlspecialchars($search); ?>"
					placeholder="Search movies...">
			</form>

			<!-- Sort link: clicking flips between ascending/descending -->
			<a class="button <?php echo $sort !== 'none' ? 'active' : ''; ?>"
			   href="<?php echo buildLink(['sort' => $sort === 'asc' ? 'desc' : 'asc']); ?>">
				<?php
					if ($sort === 'asc') echo 'Ascending ↑';
					elseif ($sort === 'desc') echo 'Descending ↓';
					else echo 'Sort';
				?>
			</a>

			<!-- Bookmarks-only toggle link -->
			<a class="button <?php echo $bookmarksOnly ? 'active' : ''; ?>"
			   href="<?php echo buildLink(['bookmarks_only' => $bookmarksOnly ? null : 1]); ?>">
				Bookmarks
			</a>
		</div>

		<div class="mock-data-box" aria-label="Movie list">
			<?php if (empty($movies)): ?>
				<p style="padding: 20px;">No movies found.</p>
			<?php else: ?>
				<?php foreach ($movies as $movie): ?>
					<?php $isBookmarked = in_array($movie['id'], $currentBookmarks); ?>
					<div class="movie-row">
						<div class="movie-info">
							<strong><?php echo htmlspecialchars($movie['name']); ?></strong>
							<span>
								<?php echo htmlspecialchars($movie['rating'] ?? 'N/A'); ?> ·
								<?php echo htmlspecialchars($movie['genres'] ?? ''); ?>
							</span>
						</div>
						<a class="bookmark-button <?php echo $isBookmarked ? 'active' : ''; ?>"
						   href="<?php echo buildLink(['toggle_bookmark' => $movie['id']]); ?>">
							<?php echo $isBookmarked ? 'Bookmarked' : 'Bookmark'; ?>
						</a>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</main>
</body>
</html>
