<?php
// Changes the text of one of the logged-in user's own comments.

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// 1. Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// 2. Only logged-in users.
require_login();

// 3. Check the CSRF token.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    json_response([
        'success' => false,
        'message' => 'Security check failed. Please refresh the page and try again.'
    ], 403);
}

// 4. The comment id must be a whole number of 1 or more.
$commentId = filter_var($_POST['comment_id'] ?? '', FILTER_VALIDATE_INT);

if ($commentId === false || $commentId < 1) {
    json_response(['success' => false, 'message' => 'Invalid comment.'], 422);
}

// 5. Stops with an error unless the comment exists AND belongs to the logged-in user.
$comment = require_own_comment($pdo, $commentId);

// 6. Read and validate the new text.
$content = $_POST['content'] ?? '';
$content = str_replace("\r\n", "\n", $content);
$content = trim($content);

if (mb_strlen($content) > MAX_COMMENT_LENGTH) {
    json_response([
        'success' => false,
        'message' => 'Comments can be at most ' . MAX_COMMENT_LENGTH . ' characters long.'
    ], 422);
}

// A comment may have empty text only if it has an image.
$imageCheck = $pdo->prepare('SELECT 1 FROM comment_images WHERE comment_id = :id');
$imageCheck->execute(['id' => $commentId]);
$hasImage = (bool) $imageCheck->fetchColumn();

if ($content === '' && !$hasImage) {
    json_response([
        'success' => false,
        'message' => 'A comment cannot be empty. Use Delete to remove it.'
    ], 422);
}

// 7. Save the change (only if the text is actually different) and read the comment back.
try {
    if ($content !== (string) ($comment['content'] ?? '')) {
        $update = $pdo->prepare(
            'UPDATE comments
             SET content = :content, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND user_id = :user_id'
        );
        $update->execute([
            'content' => ($content === '') ? null : $content,
            'id'      => $commentId,
            'user_id' => $_SESSION['user_id'],
        ]);
    }

    $select = $pdo->prepare(comment_select_sql() . ' WHERE c.id = :id');
    $select->execute(['id' => $commentId]);
    $row = $select->fetch();
} catch (PDOException $e) {
    error_log('Editing a comment failed: ' . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Something went wrong. Please try again later.'
    ], 500);
}

// 8. Send the updated comment back to the browser.
json_response([
    'success' => true,
    'message' => 'Comment updated.',
    'comment' => format_comment_row($row),
]);