<?php
require_once __DIR__ . '/../includes/db.php';
$stmt = $conn->query("SELECT count(*) as count FROM users");
$res = $stmt->fetch();
echo "Successfully connected via includes/db.php! User count: " . $res['count'] . "\n";