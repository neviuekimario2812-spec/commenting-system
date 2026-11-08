<?php
// Deletes one of the logged-in user's own comments (and its image file, if any).

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
require_own_comment($pdo, $commentId);

try {
    // 6. Remember the image files BEFORE deleting, because deleting the comment
    //    also removes its comment_images rows (ON DELETE CASCADE).
    $imageQuery = $pdo->prepare('SELECT image_path FROM comment_images WHERE comment_id = :id');
    $imageQuery->execute(['id' => $commentId]);
    $imagePaths = $imageQuery->fetchAll(PDO::FETCH_COLUMN);

    // 7. Delete the comment (the user_id check is a second safety net).
    $delete = $pdo->prepare('DELETE FROM comments WHERE id = :id AND user_id = :user_id');
    $delete->execute([
        'id'      => $commentId,
        'user_id' => $_SESSION['user_id'],
    ]);
} catch (PDOException $e) {
    error_log('Deleting a comment failed: ' . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Something went wrong. Please try again later.'
    ], 500);
}

// 8. Delete the image files from the disk (only inside our comments folder).
$uploadDir = __DIR__ . '/../../public/uploads/comments/';

foreach ($imagePaths as $imagePath) {
    if (str_starts_with($imagePath, 'uploads/comments/')) {
        $file = $uploadDir . basename($imagePath);

        if (is_file($file)) {
            unlink($file);
        }
    }
}

json_response([
    'success'    => true,
    'message'    => 'Comment deleted.',
    'comment_id' => $commentId,
]);