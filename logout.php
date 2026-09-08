<?php
session_start();
session_unset();   // clears all session variables (user_id, username, bookmarks, etc.)
session_destroy(); // ends the session completely
header('Location: index.php');
exit;
