<?php
try {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("DROP DATABASE IF EXISTS musician_db");
    echo "Database musician_db dropped successfully.\n";
} catch (PDOException $e) {
    echo "Error dropping database: " . $e->getMessage() . "\n";
}
