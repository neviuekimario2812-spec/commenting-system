<?php
// Tells the browser whether someone is logged in, and who.

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (!is_logged_in()) {
    json_response(['logged_in' => false], 401);
}

// Read the user's current details from the database.
$statement = $pdo->prepare(
    'SELECT id, email, display_name, profile_picture FROM users WHERE id = :id'
);
$statement->execute(['id' => $_SESSION['user_id']]);
$user = $statement->fetch();

// If the account no longer exists, end the session.
if (!$user) {
    $_SESSION = [];
    session_destroy();
    json_response(['logged_in' => false], 401);
}

// Keep the session copy of the name up to date.
$_SESSION['display_name'] = $user['display_name'];

json_response([
    'logged_in'  => true,
    'user'       => [
        'id'              => (int) $user['id'],
        'email'           => $user['email'],
        'display_name'    => $user['display_name'],
        'profile_picture' => $user['profile_picture'],   // null if none uploaded yet
    ],
    'csrf_token' => generate_csrf_token(),
]);