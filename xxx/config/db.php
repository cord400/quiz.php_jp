<?php
$use_db = true; // true: MySQL, false: Google Spreadsheet

if ($use_db) {
    $host = 'localhost';
    $db   = 'null';
    $user = 'null';
    $pass = 'null';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        echo "DB接続失敗: " . $e->getMessage();
        exit;
    }
} else {
    // Google Sheets API の読み込みは別途で実装
}
?>
