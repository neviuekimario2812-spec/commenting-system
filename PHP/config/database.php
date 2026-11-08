<?php
// Database connection settings (Supabase Session pooler)
// IMPORTANT: Replace the placeholder values with your own details.

const DB_HOST = 'aws-1-eu-central-1.pooler.supabase.com';            // e.g. aws-0-xxxx.pooler.supabase.com
const DB_PORT = '5432';
const DB_NAME = 'postgres';
const DB_USER = 'postgres.lzldhfrlgtozgoyyulgj';   // note the dot
const DB_PASS = '/Bga$VEKi9eXKf_';

// While learning, show detailed errors. Set to false when the site goes live.
const APP_DEBUG = true;

// The connection string (DSN). sslmode=require makes the connection encrypted.
$dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';sslmode=require';

// PDO settings
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // throw errors instead of failing silently
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // return rows as named arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                   // use real prepared statements
];

try {
    // Create the connection. $pdo is the variable other files will use.
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Record the real error in the server's error log
    error_log('Database connection failed: ' . $e->getMessage());

    http_response_code(500);

    if (APP_DEBUG) {
        die('Database connection failed: ' . $e->getMessage());
    }

    die('Sorry, something went wrong. Please try again later.');
}