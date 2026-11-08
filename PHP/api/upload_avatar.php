<?php
// Validates and saves a new profile picture for the logged-in user.

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

const MAX_AVATAR_SIZE = 2 * 1024 * 1024;   // 2 MB

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
        'message' => 'The picture is too large. Maximum size is 2 MB.'
    ], 413);
}

// 4. Check the CSRF token.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    json_response([
        'success' => false,
        'message' => 'Security check failed. Please refresh the page and try again.'
    ], 403);
}

// 5. Was a file sent, and did the upload succeed?
if (!isset($_FILES['avatar'])) {
    json_response(['success' => false, 'message' => 'Please choose a picture first.'], 400);
}

$file = $_FILES['avatar'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $message = match ($file['error']) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The picture is too large. Maximum size is 2 MB.',
        UPLOAD_ERR_NO_FILE                        => 'Please choose a picture first.',
        default                                   => 'The upload failed. Please try again.',
    };
    json_response(['success' => false, 'message' => $message], 400);
}

// 6. Check the size and that the file really came from an upload.
if ($file['size'] > MAX_AVATAR_SIZE) {
    json_response([
        'success' => false,
        'message' => 'The picture is too large. Maximum size is 2 MB.'
    ], 413);
}

if (!is_uploaded_file($file['tmp_name'])) {
    json_response(['success' => false, 'message' => 'Invalid upload.'], 400);
}

// 7. Check the REAL file type by looking at the file's contents.
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!isset($allowedTypes[$mimeType])) {
    json_response([
        'success' => false,
        'message' => 'Only JPG, PNG, and WebP images are allowed.'
    ], 415);
}

// getimagesize() returns false if the file is not a valid image.
if (getimagesize($file['tmp_name']) === false) {
    json_response([
        'success' => false,
        'message' => 'The file is not a valid image.'
    ], 415);
}

// 8. Build a unique file name. WE choose the extension, not the user.
$userId    = (int) $_SESSION['user_id'];
$extension = $allowedTypes[$mimeType];
$newName   = 'avatar_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;

$uploadDir = __DIR__ . '/../../public/uploads/avatars/';

if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
    error_log('Avatar folder is missing or not writable: ' . $uploadDir);
    json_response([
        'success' => false,
        'message' => 'Server problem: the upload folder is not available.'
    ], 500);
}

// 9. Move the file from PHP's temporary folder to its permanent place.
if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
    error_log('move_uploaded_file failed for ' . $newName);
    json_response(['success' => false, 'message' => 'Could not save the picture.'], 500);
}

$relativePath = 'uploads/avatars/' . $newName;   // this is what we store in the database

// 10. Remember the old picture so we can delete it, then save the new path.
try {
    $select = $pdo->prepare('SELECT profile_picture FROM users WHERE id = :id');
    $select->execute(['id' => $userId]);
    $oldPath = $select->fetchColumn();

    $update = $pdo->prepare('UPDATE users SET profile_picture = :path WHERE id = :id');
    $update->execute(['path' => $relativePath, 'id' => $userId]);
} catch (PDOException $e) {
    unlink($uploadDir . $newName);      // remove the file we just saved
    error_log('Avatar database update failed: ' . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Something went wrong. Please try again later.'
    ], 500);
}

// 11. Delete the previous picture file (only if it is inside our avatars folder).
if (!empty($oldPath) && str_starts_with($oldPath, 'uploads/avatars/')) {
    $oldFile = $uploadDir . basename($oldPath);

    if (is_file($oldFile)) {
        unlink($oldFile);
    }
}

json_response([
    'success'         => true,
    'message'         => 'Profile picture updated.',
    'profile_picture' => $relativePath,
]);