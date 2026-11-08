<?php
// Shared helper functions used by many PHP files.

// The longest comment allowed, in characters.
const MAX_COMMENT_LENGTH = 1000;

// Starts a PHP session with safer cookie settings.
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,                           // cookie ends when the browser closes
            'path'     => '/',
            'httponly' => true,                        // JavaScript cannot read the cookie
            'samesite' => 'Lax',                       // limits cross-site sending of the cookie
            'secure'   => !empty($_SERVER['HTTPS']),   // HTTPS-only when the site uses HTTPS
        ]);
        session_start();
    }
}

// Creates (or reuses) the CSRF token stored in the session.
function generate_csrf_token(): string
{
    start_secure_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// Checks that the token sent by the form matches the one in the session.
function verify_csrf_token($token): bool
{
    start_secure_session();

    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

// Sends a JSON reply to the browser and stops the script.
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

// Returns true if a user is logged in (their id is stored in the session).
function is_logged_in(): bool
{
    start_secure_session();

    return !empty($_SESSION['user_id']);
}

// Stops the script with a 401 reply if nobody is logged in.
// We call this at the top of every protected PHP file.
function require_login(): void
{
    if (!is_logged_in()) {
        json_response([
            'success' => false,
            'message' => 'You must be logged in to do this.'
        ], 401);
    }
}

// Converts a database timestamp (stored in UTC) into text like 2026-11-08T10:15:00Z,
// which JavaScript understands and can show in the visitor's own time zone.
function format_utc_date(string $timestamp): string
{
    $date = new DateTime($timestamp, new DateTimeZone('UTC'));

    return $date->format('Y-m-d\TH:i:s\Z');
}

// The SELECT part shared by every query that loads comments.
// Callers add their own WHERE / ORDER BY at the end.
function comment_select_sql(): string
{
    return 'SELECT c.id, c.user_id, c.content, c.created_at, c.updated_at,
                   u.display_name, u.profile_picture, ci.image_path
            FROM comments c
            JOIN users u ON u.id = c.user_id
            LEFT JOIN comment_images ci ON ci.comment_id = c.id';
}

// Turns one database row (a comment joined with its author) into the
// array we send to the browser. One place decides the shape of a comment.
function format_comment_row(array $row): array
{
    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);

    return [
        'id'              => (int) $row['id'],
        'content'         => $row['content'],                         // null for image-only comments
        'created_at'      => format_utc_date($row['created_at']),
        'is_edited'       => !empty($row['updated_at']),              // true after the text was changed
        'is_owner'        => (int) $row['user_id'] === $currentUserId, // only used to show or hide buttons
        'display_name'    => $row['display_name'],
        'profile_picture' => $row['profile_picture'],                 // null if the author has no picture
        'image_path'      => $row['image_path'] ?? null,              // null if the comment has no image
    ];
}

// Finds a comment and makes sure it belongs to the logged-in user.
// If the comment does not exist (404) or belongs to someone else (403),
// it sends an error reply and stops. Otherwise it returns the comment's row.
function require_own_comment(PDO $pdo, int $commentId): array
{
    $statement = $pdo->prepare('SELECT id, user_id, content FROM comments WHERE id = :id');
    $statement->execute(['id' => $commentId]);
    $comment = $statement->fetch();

    if (!$comment) {
        json_response([
            'success' => false,
            'message' => 'This comment no longer exists.'
        ], 404);
    }

    if ((int) $comment['user_id'] !== (int) $_SESSION['user_id']) {
        json_response([
            'success' => false,
            'message' => 'You can only change your own comments.'
        ], 403);
    }

    return $comment;
}

// Checks an uploaded image file (one entry of $_FILES).
// It looks at the REAL contents of the file, never at the name the user gave it.
// Returns ['success' => true, 'extension' => 'jpg'] when the image is acceptable,
// or ['success' => false, 'message' => '...', 'status' => 4xx] when it is not.
function check_uploaded_image(array $file, int $maxBytes): array
{
    $maxMb = round($maxBytes / (1024 * 1024));

    // 1. Did the upload itself work?
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The image is too large. Maximum size is ' . $maxMb . ' MB.',
            UPLOAD_ERR_NO_FILE                        => 'Please choose an image first.',
            default                                   => 'The upload failed. Please try again.',
        };

        return ['success' => false, 'message' => $message, 'status' => 400];
    }

    // 2. Is it small enough, and did it really come from an HTTP upload?
    if ($file['size'] > $maxBytes) {
        return [
            'success' => false,
            'message' => 'The image is too large. Maximum size is ' . $maxMb . ' MB.',
            'status'  => 413,
        ];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Invalid upload.', 'status' => 400];
    }

    // 3. Check the real file type. We choose the extension ourselves from this list.
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!isset($allowedTypes[$mimeType])) {
        return [
            'success' => false,
            'message' => 'Only JPG, PNG, and WebP images are allowed.',
            'status'  => 415,
        ];
    }

    // 4. getimagesize() returns false if the file is not a valid image.
    if (getimagesize($file['tmp_name']) === false) {
        return ['success' => false, 'message' => 'The file is not a valid image.', 'status' => 415];
    }

    return ['success' => true, 'extension' => $allowedTypes[$mimeType]];
}