<?php
// Saves a new comment (text, image, or both) for the logged-in user.

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

const MAX_COMMENT_IMAGE_SIZE = 5 * 1024 * 1024;   // 5 MB

// 1. Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// 2. Only logged-in users.
require_login();

// 3. If the upload was bigger than PHP's overall limit, PHP discards everything.
if (empty($_POST) && empty($_FILES) && !empty($_SERVER['CONTENT_LENGTH'])) {
    json_response([
        'success' => false,
        'message' => 'The image is too large. Maximum size is 5 MB.'
    ], 413);
}

// 4. Check the CSRF token.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    json_response([
        'success' => false,
        'message' => 'Security check failed. Please refresh the page and try again.'
    ], 403);
}

// 5. Read the comment text.
$content = $_POST['content'] ?? '';
$content = str_replace("\r\n", "\n", $content);   // browsers send new lines as \r\n; make them \n
$content = trim($content);

// Was an image sent?
$hasImage = isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;

// 6. A comment needs text, an image, or both.
if ($content === '' && !$hasImage) {
    json_response([
        'success' => false,
        'message' => 'Please write something or choose an image.'
    ], 422);
}

if (mb_strlen($content) > MAX_COMMENT_LENGTH) {
    json_response([
        'success' => false,
        'message' => 'Comments can be at most ' . MAX_COMMENT_LENGTH . ' characters long.'
    ], 422);
}

// 7. If there is an image, check it BEFORE saving anything.
$extension = null;

if ($hasImage) {
    $check = check_uploaded_image($_FILES['image'], MAX_COMMENT_IMAGE_SIZE);

    if (!$check['success']) {
        json_response(['success' => false, 'message' => $check['message']], $check['status']);
    }

    $extension = $check['extension'];
}

$userId = (int) $_SESSION['user_id'];

// 8. Save the image file (if any) with a unique name.
$imagePath = null;    // what we store in the database
$savedFile = null;    // where the file is on disk

if ($hasImage) {
    $uploadDir = __DIR__ . '/../../public/uploads/comments/';

    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        error_log('Comment image folder is missing or not writable: ' . $uploadDir);
        json_response([
            'success' => false,
            'message' => 'Server problem: the upload folder is not available.'
        ], 500);
    }

    $imageName = 'comment_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $savedFile = $uploadDir . $imageName;

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $savedFile)) {
        error_log('move_uploaded_file failed for ' . $imageName);
        json_response(['success' => false, 'message' => 'Could not save the image.'], 500);
    }

    $imagePath = 'uploads/comments/' . $imageName;
}

// 9. Save everything in ONE transaction: either all of it is saved, or none of it.
try {
    $pdo->beginTransaction();

    // Image-only comments store NULL as their text.
    $insert = $pdo->prepare(
        'INSERT INTO comments (user_id, content)
         VALUES (:user_id, :content)
         RETURNING id'
    );
    $insert->execute([
        'user_id' => $userId,
        'content' => ($content === '') ? null : $content,
    ]);

    $commentId = (int) $insert->fetchColumn();

    if ($imagePath !== null) {
        $insertImage = $pdo->prepare(
            'INSERT INTO comment_images (comment_id, image_path)
             VALUES (:comment_id, :image_path)'
        );
        $insertImage->execute([
            'comment_id' => $commentId,
            'image_path' => $imagePath,
        ]);
    }

    // Read the new comment back with its author and image.
    $select = $pdo->prepare(comment_select_sql() . ' WHERE c.id = :id');
    $select->execute(['id' => $commentId]);
    $row = $select->fetch();

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();                 // undo the partly saved data
    }

    if ($savedFile !== null && is_file($savedFile)) {
        unlink($savedFile);               // remove the image file we saved
    }

    error_log('Adding a comment failed: ' . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Something went wrong. Please try again later.'
    ], 500);
}

// 10. Send the new comment back to the browser.
json_response([
    'success' => true,
    'message' => 'Comment posted.',
    'comment' => format_comment_row($row),
], 201);