<?php
// config/db.php
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Tự động kích hoạt session hệ thống phục vụ login
}

$host = 'localhost';
$db   = 'campus_final'; // Tên database đã hiện lên trong Database Client
$user = 'root';
$pass = '';            // XAMPP mặc định để trống hoàn toàn
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>