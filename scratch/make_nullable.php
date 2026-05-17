<?php
require_once 'includes/db.php';
try {
    // 1. Drop foreign key constraint first
    $conn->exec("ALTER TABLE reviews DROP FOREIGN KEY reviews_ibfk_1");
} catch (Exception $e) {
    echo "FK drop warning: " . $e->getMessage() . "\n";
}

try {
    // 2. Make booking_id NULLable
    $conn->exec("ALTER TABLE reviews MODIFY booking_id INT NULL");
    echo "booking_id modified to NULL successfully!\n";
} catch (Exception $e) {
    echo "Error modifying booking_id: " . $e->getMessage() . "\n";
}

try {
    // 3. Re-add foreign key constraint with NULL allowed
    $conn->exec("ALTER TABLE reviews ADD CONSTRAINT reviews_ibfk_1 FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL");
    echo "Foreign key constraint re-added successfully!\n";
} catch (Exception $e) {
    echo "Error adding foreign key: " . $e->getMessage() . "\n";
}
?>
