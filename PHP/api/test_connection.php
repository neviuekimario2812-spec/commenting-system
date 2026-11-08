<?php
// Temporary file: checks that PHP can talk to the database.
// We will delete it later.

require_once __DIR__ . '/../config/database.php';

// Run a simple query: count the rows in the users table
$statement = $pdo->query('SELECT COUNT(*) AS total FROM users');
$result = $statement->fetch();

echo 'Connection successful! ';
echo 'Number of users in the database: ' . $result['total'];