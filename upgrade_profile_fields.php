<?php
require_once 'includes/db.php';
try {
    // Add banner and bio to community_profiles
    $conn->exec("ALTER TABLE community_profiles ADD COLUMN IF NOT EXISTS banner VARCHAR(255) DEFAULT NULL AFTER avatar");
    $conn->exec("ALTER TABLE community_profiles ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL AFTER banner");
    
    echo "Database upgraded successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
