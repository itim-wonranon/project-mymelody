<?php
require_once 'includes/db.php';
try {
    // 1. Create reposts table
    $conn->exec("CREATE TABLE IF NOT EXISTS reposts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        post_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_repost (user_id, post_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
    )");

    echo "Repost system database ready";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
