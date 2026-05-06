<?php
    $host = 'localhost';
    $dbname = 'parkease_db';
    $username = 'root';
    $password = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    function requireLogin() {
        if (!isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }
?>