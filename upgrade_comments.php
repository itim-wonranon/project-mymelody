<?php
require_once 'includes/db.php';
try {
    // 1. Add parent_id to comments for replies
    $conn->exec("ALTER TABLE comments ADD COLUMN parent_id INT DEFAULT NULL AFTER post_id");
    $conn->exec("ALTER TABLE comments ADD CONSTRAINT fk_comment_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE");

    // 2. Create comment_reactions table
    $conn->exec("CREATE TABLE IF NOT EXISTS comment_reactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comment_id INT NOT NULL,
        user_id INT NOT NULL,
        reaction_type ENUM('like', 'heart', 'haha', 'wow', 'sad', 'angry') DEFAULT 'like',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_comment_reaction (comment_id, user_id),
        FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    echo "Comment system upgraded successfully";
} catch (PDOException $e) {
    echo "Notice/Error: " . $e->getMessage(); // parent_id might already exist
}
?>
