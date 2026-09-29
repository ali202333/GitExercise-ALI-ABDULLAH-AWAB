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

// ---- Handle a bookmark toggle, if one was clicked ----
if (isset($_GET['toggle_bookmark'])) {
    $movieId = (int) $_GET['toggle_bookmark'];

    if (!isset($_SESSION['bookmarks'])) {
        $_SESSION['bookmarks'] = [];
    }

    if (in_array($movieId, $_SESSION['bookmarks'])) {
        $_SESSION['bookmarks'] = array_diff($_SESSION['bookmarks'], [$movieId]);
    } else {
        $_SESSION['bookmarks'][] = $movieId;
    }

    $params = $_GET;
    unset($params['toggle_bookmark']);
    header('Location: index.php?' . http_build_query($params));
    exit;
}

// ---- Read search/sort/genre/bookmarks/page choices from the URL ----
$search        = trim($_GET['search'] ?? '');
$genre         = $_GET['genre'] ?? 'all';
$sort          = $_GET['sort'] ?? 'none';
$bookmarksOnly = isset($_GET['bookmarks_only']);
$page          = max(0, (int) ($_GET['page'] ?? 0));
$pageSize      = 50;

// A view is "paginated" only when nothing is filtered - same rule as the original prototype
$isPaginatedView = ($search === '' && $genre === 'all' && !$bookmarksOnly);

// ---- Build the WHERE conditions and params shared by both queries ----
$where  = '1=1';
$params = [];

if ($search !== '') {
    $where .= ' AND name LIKE :search';
    $params['search'] = '%' . $search . '%';
}

if ($genre !== 'all') {
    $where .= ' AND genres LIKE :genre';
    $params['genre'] = '%' . $genre . '%';
}

if ($bookmarksOnly) {
    $bookmarked = $_SESSION['bookmarks'] ?? [];
    if (empty($bookmarked)) {
        $where .= ' AND 1=0';
    } else {
        $placeholders = [];
        foreach ($bookmarked as $index => $id) {
            $key = "bm$index";
            $placeholders[] = ":$key";
            $params[$key] = $id;
        }
        $where .= ' AND id IN (' . implode(',', $placeholders) . ')';
    }
}

$orderBy = $sort === 'asc' ? 'name ASC' : ($sort === 'desc' ? 'name DESC' : 'id ASC');

// ---- Get the total count, only needed to know when to disable "Next" ----
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM titles WHERE $where");
$countStmt->execute($params);
$totalCount = (int) $countStmt->fetchColumn();

// ---- Build the actual movie query, adding LIMIT/OFFSET only in paginated view ----
$sql = "SELECT id, name, rating, genres, movie_url FROM titles WHERE $where ORDER BY $orderBy";

if ($isPaginatedView) {
    $sql .= ' LIMIT :limit OFFSET :offset';
}

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
if ($isPaginatedView) {
    $stmt->bindValue('limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue('offset', $page * $pageSize, PDO::PARAM_INT);
}
$stmt->execute();
$movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Helper to build a link that keeps existing filters, with optional overrides ----
function buildLink($overrides) {
    $params = array_merge($_GET, $overrides);
    return 'index.php?' . http_build_query($params);
}

$currentBookmarks = $_SESSION['bookmarks'] ?? [];

// Same genre list as the prototype's dropdown
$genres = ['Action','Adult','Adventure','Animation','Biography','Comedy','Crime','Documentary','Drama','Family','Fantasy','Film-Noir','Game-Show','History','Horror','Music','Musical','Mystery','News','Reality-TV','Romance','Sci-Fi','Short','Sport','Talk-Show','Thriller','War','Western'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>S-CoreBox</title>
	<style>
		body { margin: 0; background: #ffffff; color: #222; font-family: Arial, sans-serif; }
		.brand { position: absolute; top: 20px; right: 24px; font-size: 1.25rem; font-weight: bold; }
		.account-actions { position: absolute; top: 58px; right: 24px; display: flex; align-items: center; gap: 8px; }
		.sign-in-button, .sign-up-button {
			padding: 10px 18px; border: 1px solid #cfcfcf; border-radius: 6px;
			background: #ffffff; color: #222; font: inherit; cursor: pointer; text-decoration: none;
		}
		header { padding: 80px 24px 24px; border-bottom: 1px solid #e5e5e5; }
		main { width: 75%; min-height: calc(100vh - 151px); padding: 32px 24px; box-sizing: border-box; }
		.main-actions { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 32px; }
		.main-actions select, .main-actions a.button, .search-bar {
			width: 100%; box-sizing: border-box; padding: 12px 20px; border: 1px solid #cfcfcf; border-radius: 6px;
			background: #ffffff; color: #222; font: inherit; cursor: pointer; text-decoration: none; text-align: center; display: block;
		}
		.search-bar { grid-column: 1 / -1; text-align: left; }
		.main-actions a.active { border-color: #222; background: #222; color: #ffffff; }
		.mock-data-box { max-height: 360px; overflow-y: auto; border: 1px solid #cfcfcf; border-radius: 6px; }
		.movie-row { display: flex; align-items: stretch; border-bottom: 1px solid #e5e5e5; }
		.movie-row:last-child { border-bottom: 0; }
		.movie-link { flex: 1; padding: 16px 20px; color: #222; font-weight: bold; text-decoration: none; }
		.movie-link:hover { background: #dedede; }
		.movie-rating { display: flex; align-items: center; padding: 12px 16px; border-left: 1px solid #e5e5e5; color: #555; font-weight: bold; white-space: nowrap; }
		.bookmark-button { width: 110px; padding: 12px; border: 0; border-left: 1px solid #e5e5e5; background: #ffffff; color: #222; font: inherit; cursor: pointer; text-decoration: none; display: flex; align-items: center; justify-content: center; text-align: center; }
		.bookmark-button.active { background: #dedede; font-weight: bold; }
		.pagination-controls { display: flex; gap: 12px; margin-top: 12px; }
		.pagination-button { flex: 1; padding: 12px 20px; border: 1px solid #cfcfcf; border-radius: 6px; background: #ffffff; color: #222; font: inherit; cursor: pointer; text-decoration: none; text-align: center; display: block; }
		.pagination-button.disabled { pointer-events: none; opacity: 0.5; }
		form { display: contents; }
	</style>
</head>
<body>
	<div class="brand">S-CoreBox</div>
	<nav class="account-actions" aria-label="Account actions">
		<?php if ($isLoggedIn): ?>
			<span>Hi, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
			<a class="sign-in-button" href="logout.php">Sign Out</a>
		<?php else: ?>
			<a class="sign-in-button" href="signin.php">Sign In</a>
			<a class="sign-up-button" href="signup.php">Sign Up</a>
		<?php endif; ?>
	</nav>

	<header>
		<h1>S-CoreBox</h1>
	</header>

	<main>
		<div class="main-actions" role="group" aria-label="Content controls">
			<!-- Genre filter: a plain GET form, changing the dropdown needs a "Go" since there's no JS auto-submit -->
			<form method="get" action="index.php" style="display:contents;">
				<?php if ($search !== ''): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
				<?php if ($sort !== 'none'): ?><input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>"><?php endif; ?>
				<?php if ($bookmarksOnly): ?><input type="hidden" name="bookmarks_only" value="1"><?php endif; ?>
				<select name="genre" onchange="this.form.submit()" aria-label="Filter by genre">
					<option value="all" <?php echo $genre === 'all' ? 'selected' : ''; ?>>All genres</option>
					<?php foreach ($genres as $g): ?>
						<option value="<?php echo htmlspecialchars($g); ?>" <?php echo $genre === $g ? 'selected' : ''; ?>><?php echo htmlspecialchars($g); ?></option>
					<?php endforeach; ?>
				</select>
			</form>

			<a class="button <?php echo $sort !== 'none' ? 'active' : ''; ?>"
			   href="<?php echo buildLink(['sort' => $sort === 'asc' ? 'desc' : 'asc', 'page' => 0]); ?>">
				<?php echo $sort === 'asc' ? 'Ascending ↑' : ($sort === 'desc' ? 'Descending ↓' : 'Sort'); ?>
			</a>

			<a class="button <?php echo $bookmarksOnly ? 'active' : ''; ?>"
			   href="<?php echo buildLink(['bookmarks_only' => $bookmarksOnly ? null : 1, 'page' => 0]); ?>">
				Watch Later
			</a>

			<form method="get" action="index.php" style="display:contents;">
				<?php if ($genre !== 'all'): ?><input type="hidden" name="genre" value="<?php echo htmlspecialchars($genre); ?>"><?php endif; ?>
				<?php if ($sort !== 'none'): ?><input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>"><?php endif; ?>
				<?php if ($bookmarksOnly): ?><input type="hidden" name="bookmarks_only" value="1"><?php endif; ?>
				<input class="search-bar" type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search movies...">
			</form>
		</div>

		<div class="mock-data-box" aria-label="Movie data">
			<?php if (empty($movies)): ?>
				<p style="padding: 20px;">No movies found.</p>
			<?php else: ?>
				<?php foreach ($movies as $movie): ?>
					<?php $isBookmarked = in_array($movie['id'], $currentBookmarks); ?>
					<div class="movie-row">
						<a class="movie-link" href="details.php?id=<?php echo (int) $movie['id']; ?>">
							<?php echo (int) $movie['id']; ?>. <?php echo htmlspecialchars($movie['name']); ?>
						</a>
						<span class="movie-rating">&#9733; <?php echo htmlspecialchars($movie['rating'] ?? 'N/A'); ?></span>
						<a class="bookmark-button <?php echo $isBookmarked ? 'active' : ''; ?>"
						   href="<?php echo buildLink(['toggle_bookmark' => $movie['id']]); ?>">
							<?php echo $isBookmarked ? 'Saved' : 'Watch Later'; ?>
						</a>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<?php if ($isPaginatedView): ?>
			<div class="pagination-controls">
				<a class="pagination-button <?php echo $page === 0 ? 'disabled' : ''; ?>"
				   href="<?php echo buildLink(['page' => max(0, $page - 1)]); ?>">Back</a>
				<a class="pagination-button <?php echo ($page + 1) * $pageSize >= $totalCount ? 'disabled' : ''; ?>"
				   href="<?php echo buildLink(['page' => $page + 1]); ?>">Next</a>
			</div>
		<?php endif; ?>
	</main>
</body>
</html>
