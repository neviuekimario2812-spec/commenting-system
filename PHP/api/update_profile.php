<?php
// Changes the logged-in user's display name.

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

// 4. Validate the new name.
$displayName = trim($_POST['display_name'] ?? '');

if (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 50) {
    json_response([
        'success' => false,
        'message' => 'Display name must be between 2 and 50 characters.'
    ], 422);
}

// 5. Update ONLY the logged-in user's row (the id comes from the session).
$statement = $pdo->prepare(
    'UPDATE users SET display_name = :display_name WHERE id = :id'
);
$statement->execute([
    'display_name' => $displayName,
    'id'           => $_SESSION['user_id'],
]);

$_SESSION['display_name'] = $displayName;

json_response([
    'success'      => true,
    'message'      => 'Display name updated.',
    'display_name' => $displayName,
]);