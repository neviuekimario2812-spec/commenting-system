<?php
// Returns all comments, oldest first, each with its author's name and picture
// and its image (if it has one).

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// 1. Only accept GET requests.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// 2. Only logged-in users.
require_login();

// 3. Read the comments. There is no user input in this query, so query() is safe here.
$statement = $pdo->query(
    comment_select_sql() . ' ORDER BY c.created_at ASC, c.id ASC'
);

$comments = [];

foreach ($statement->fetchAll() as $row) {
    $comments[] = format_comment_row($row);
}

json_response([
    'success'  => true,
    'comments' => $comments,
]);