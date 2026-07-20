<?php
$servername = "localhost";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$servername", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS musician_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    $conn->exec($sql);
    echo "Database created successfully\n";
    
    $conn->exec("USE musician_db");

    // 1. users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'musician', 'employer') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    
    // 2. musician_profiles table
    $sql = "CREATE TABLE IF NOT EXISTS musician_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        profile_image VARCHAR(255) DEFAULT 'default_avatar.png',
        bio TEXT,
        band_type ENUM('solo', 'band') NOT NULL,
        genres VARCHAR(255),
        rate VARCHAR(100),
        location VARCHAR(255),
        availability_info TEXT,
        is_verified BOOLEAN DEFAULT FALSE,
        rating_score DECIMAL(3,2) DEFAULT 0.00,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // 3. employer_profiles table
    $sql = "CREATE TABLE IF NOT EXISTS employer_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        profile_image VARCHAR(255) DEFAULT 'default_avatar.png',
        company_name VARCHAR(255),
        details TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // 4. portfolios table
    $sql = "CREATE TABLE IF NOT EXISTS portfolios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        musician_id INT NOT NULL,
        type ENUM('video', 'audio', 'image') NOT NULL,
        link VARCHAR(255) NOT NULL,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (musician_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // 5. posts table
    $sql = "CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // 6. bookings table
    $sql = "CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employer_id INT NOT NULL,
        musician_id INT NOT NULL,
        booking_date DATE NOT NULL,
        start_time TIME,
        end_time TIME,
        details TEXT,
        status ENUM('pending', 'confirmed', 'rejected', 'completed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (musician_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // 7. reviews table
    $sql = "CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT DEFAULT NULL,
        employer_id INT NOT NULL,
        musician_id INT NOT NULL,
        rating INT NOT NULL CHECK(rating >= 1 AND rating <= 5),
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (musician_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // 8. disputes table
    $sql = "CREATE TABLE IF NOT EXISTS disputes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        issue_type VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        status ENUM('open', 'resolved') DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Insert Default Admin
    // Password is 'admin123' hashed
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password, role) 
            SELECT 'admin', 'admin@example.com', '$admin_password', 'admin' 
            WHERE NOT EXISTS (SELECT id FROM users WHERE username = 'admin')";
    $conn->exec($sql);

    echo "Tables created successfully.\nAdmin user created (username: admin, password: admin123)\n";

} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
