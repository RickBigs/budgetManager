<?php
$host = 'localhost';
$dbname = 'budget_manager';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}
?>
