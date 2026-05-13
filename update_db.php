<?php
require_once 'includes/db.php';

try {
    // 1. Update `users` table
    $conn->exec("ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS first_name VARCHAR(100) DEFAULT NULL AFTER username,
        ADD COLUMN IF NOT EXISTS last_name VARCHAR(100) DEFAULT NULL AFTER first_name,
        ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL AFTER email
    ");
    echo "Users table updated.<br>";

    // 2. Update `musician_profiles` table
    $conn->exec("ALTER TABLE musician_profiles
        MODIFY COLUMN band_type ENUM('solo', 'duo', 'band') NULL DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS age INT DEFAULT NULL AFTER bio,
        ADD COLUMN IF NOT EXISTS instruments VARCHAR(255) DEFAULT NULL AFTER age,
        ADD COLUMN IF NOT EXISTS band_members TEXT DEFAULT NULL AFTER instruments,
        ADD COLUMN IF NOT EXISTS pricing_type ENUM('day', 'hour') DEFAULT 'hour' AFTER rate,
        ADD COLUMN IF NOT EXISTS work_areas TEXT DEFAULT NULL AFTER location,
        ADD COLUMN IF NOT EXISTS availability_days TEXT DEFAULT NULL AFTER availability_info,
        ADD COLUMN IF NOT EXISTS availability_times TEXT DEFAULT NULL AFTER availability_days
    ");
    echo "Musician_profiles table updated.<br>";

    // 3. Update `portfolios` table
    $conn->exec("ALTER TABLE portfolios
        MODIFY COLUMN type ENUM('video', 'audio', 'image', 'link') NOT NULL
    ");
    echo "Portfolios table updated.<br>";

    echo "Database schema successfully updated!";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?>
