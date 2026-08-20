<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=musician_db;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Connected successfully to musician_db!\n";
    echo "Tables in musician_db:\n";
    print_r($tables);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
