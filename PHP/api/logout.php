<?php
// Handles logout.

require_once __DIR__ . '/../includes/functions.php';

// 1. Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// 2. Only logged-in users can log out.
require_login();

// 3. Check the CSRF token.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    json_response([
        'success' => false,
        'message' => 'Security check failed. Please refresh the page and try again.'
    ], 403);
}

// 4. Empty the session data.
$_SESSION = [];

// 5. Delete the session cookie in the browser.
$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires'  => time() - 42000,
    'path'     => $params['path'],
    'domain'   => $params['domain'],
    'secure'   => $params['secure'],
    'httponly' => $params['httponly'],
    'samesite' => 'Lax',
]);

// 6. Destroy the session on the server.
session_destroy();

json_response([
    'success' => true,
    'message' => 'You have been logged out.'
]);