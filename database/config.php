<?php

$DB_HOST = 'localhost';
$DB_NAME = 'villaflores_gaming';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {

    die('Database connection failed. Please try again later.');
}

define('CAFE_GCASH_NUMBER', '0935 908 9023');
define('CAFE_MAYA_NUMBER', '0935 908 9023');