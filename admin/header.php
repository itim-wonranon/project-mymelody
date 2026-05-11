<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../includes/db.php';
// Set default timezone
date_default_timezone_set('Asia/Bangkok');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MuseConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts (Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f6f9; }
        .sidebar { min-height: 100vh; background-color: #343a40; }
        .sidebar a { color: #c2c7d0; padding: 15px; display: block; text-decoration: none; border-bottom: 1px solid #4f5962; }
        .sidebar a:hover, .sidebar a.active { background-color: #007bff; color: white; }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar d-flex flex-column">
            <div class="p-3 text-center text-white border-bottom border-secondary">
                <i class="fas fa-shield-alt fa-2x mb-2 text-primary"></i>
                <h5>Admin Panel</h5>
            </div>
            <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
            <a href="verify.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'verify.php' ? 'active' : ''; ?>"><i class="fas fa-user-check me-2"></i> ยืนยันตัวตนนักดนตรี</a>
            <a href="disputes.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'disputes.php' ? 'active' : ''; ?>"><i class="fas fa-exclamation-triangle me-2"></i> แจ้งปัญหา (Disputes)</a>
            <a href="../index.php" class="mt-auto bg-dark"><i class="fas fa-globe me-2"></i> กลับไปหน้าเว็บหลัก</a>
            <a href="../logout.php" class="text-danger bg-dark border-0"><i class="fas fa-sign-out-alt me-2"></i> ออกจากระบบ</a>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-10 p-4">
