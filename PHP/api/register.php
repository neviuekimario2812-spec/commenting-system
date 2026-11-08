<?php
// Handles user registration.

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// 1. Only accept form submissions (POST), not people typing the URL in a browser.
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
$displayName     = trim($_POST['display_name'] ?? '');
$email           = strtolower(trim($_POST['email'] ?? ''));
$password        = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// 4. Validate every field. Collect all problems in a list.
$errors = [];

if (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 50) {
    $errors[] = 'Display name must be between 2 and 50 characters.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    $errors[] = 'Please enter a valid email address.';
}

if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters long.';
}

if (strlen($password) > 72) {
    $errors[] = 'Password must not be longer than 72 characters.';
}

if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    json_response([
        'success' => false,
        'message' => implode(' ', $errors)
    ], 422);
}

// 5. Check whether the email is already registered.
$check = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$check->execute(['email' => $email]);

if ($check->fetch()) {
    json_response([
        'success' => false,
        'message' => 'This email address is already registered.'
    ], 409);
}

// 6. Hash the password and save the new user.
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $insert = $pdo->prepare(
        'INSERT INTO users (email, password_hash, display_name)
         VALUES (:email, :password_hash, :display_name)'
    );

    $insert->execute([
        'email'         => $email,
        'password_hash' => $passwordHash,
        'display_name'  => $displayName,
    ]);
} catch (PDOException $e) {
    // 23505 is PostgreSQL's code for "duplicate value in a UNIQUE column".
    // This can happen if two people register the same email at the same moment.
    if ($e->getCode() === '23505') {
        json_response([
            'success' => false,
            'message' => 'This email address is already registered.'
        ], 409);
    }

    error_log('Registration failed: ' . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Something went wrong. Please try again later.'
    ], 500);
}

// 7. Success.
json_response([
    'success' => true,
    'message' => 'Account created successfully. You can now log in.'
], 201);