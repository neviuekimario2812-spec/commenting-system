<?php
// Handles user login.

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// 1. Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// 2. Check the CSRF token.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    json_response([
        'success' => false,
        'message' => 'Security check failed. Please refresh the page and try again.'
    ], 403);
}

// 3. Read the submitted values.
$email    = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    json_response([
        'success' => false,
        'message' => 'Please enter your email and password.'
    ], 422);
}

// 4. Look up the user by email.
$statement = $pdo->prepare(
    'SELECT id, password_hash, display_name FROM users WHERE email = :email'
);
$statement->execute(['email' => $email]);
$user = $statement->fetch();

// 5. Check the password. The same message is used whether the email
//    does not exist or the password is wrong.
if (!$user || !password_verify($password, $user['password_hash'])) {
    json_response([
        'success' => false,
        'message' => 'Invalid email or password.'
    ], 401);
}

// 6. Login is correct: start a fresh session.
session_regenerate_id(true);          // new session ID, old one deleted
unset($_SESSION['csrf_token']);       // a new CSRF token will be created for the logged-in session

$_SESSION['user_id']      = (int) $user['id'];
$_SESSION['display_name'] = $user['display_name'];

json_response([
    'success' => true,
    'message' => 'Login successful.'
]);