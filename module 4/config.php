<?php
$host = 'localhost';
$user = 'admin';
$pass = 'admin'; // ваш пароль
$db_name = 'work_auth';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

// Константы для эмулятора
define('API_URL', 'http://localhost:4444/TransferSimulator/fullName');
?>