<?php
require_once 'includes/db.php';

try {
    // 1. Update `posts` table
    $conn->exec("ALTER TABLE posts 
        ADD COLUMN IF NOT EXISTS type ENUM('text', 'image', 'video', 'poll') DEFAULT 'text' AFTER user_id,
        ADD COLUMN IF NOT EXISTS media_url VARCHAR(255) DEFAULT NULL AFTER content,
        ADD COLUMN IF NOT EXISTS feeling VARCHAR(100) DEFAULT NULL AFTER media_url,
        ADD COLUMN IF NOT EXISTS location VARCHAR(255) DEFAULT NULL AFTER feeling,
        ADD COLUMN IF NOT EXISTS poll_question VARCHAR(255) DEFAULT NULL AFTER location,
        ADD COLUMN IF NOT EXISTS poll_options JSON DEFAULT NULL AFTER poll_question
    ");
    echo "Posts table updated.<br>";

    // 2. Create `community_profiles` table
    $conn->exec("CREATE TABLE IF NOT EXISTS community_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        display_name VARCHAR(100),
        community_username VARCHAR(50) UNIQUE,
        avatar VARCHAR(255) DEFAULT 'default_avatar.png',
        banner VARCHAR(255) DEFAULT NULL,
        bio TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Community_profiles table created.<br>";

    // 3. Create `comments` table
    $conn->exec("CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Comments table created.<br>";

    // 4. Create `post_reactions` table
    $conn->exec("CREATE TABLE IF NOT EXISTS post_reactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        reaction_type ENUM('like', 'heart', 'haha', 'sad', 'angry', 'wow') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY user_post_reaction (user_id, post_id),
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Post_reactions table created.<br>";

    // 5. Create `poll_votes` table
    $conn->exec("CREATE TABLE IF NOT EXISTS poll_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        option_index INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY user_post_vote (user_id, post_id),
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Poll_votes table created.<br>";

    echo "Database schema v2 successfully updated!";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?>
