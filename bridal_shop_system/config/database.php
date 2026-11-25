<?php
declare(strict_types=1);

$host = 'localhost';
$dbname = 'online_bridal_shop_db';
$dbuser = 'root';
$dbpass = ''; // Change this if your MySQL root account has a password.

$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $dbuser, $dbpass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Check config/database.php and import schema.sql.');
}
