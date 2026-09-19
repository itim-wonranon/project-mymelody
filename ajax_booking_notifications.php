<?php
require_once 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo '<div class="text-center py-4 text-secondary"><i class="fas fa-lock mb-2 fs-3"></i><br>กรุณาเข้าสู่ระบบ</div>';
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    if ($role === 'musician') {
        $stmt = $conn->prepare("
            SELECT b.*, u.username as other_name 
            FROM bookings b 
            JOIN users u ON b.employer_id = u.id 
            WHERE b.musician_id = ? 
            ORDER BY b.created_at DESC LIMIT 5
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT b.*, u.username as other_name 
            FROM bookings b 
            JOIN users u ON b.musician_id = u.id 
            WHERE b.employer_id = ? 
            ORDER BY b.created_at DESC LIMIT 5
        ");
    }
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll();
    
    if (count($bookings) === 0) {
        echo '<div class="text-center py-5 text-secondary"><i class="far fa-folder-open mb-3 fs-1 opacity-50"></i><br>ไม่มีการแจ้งเตือนคิวงานใหม่</div>';
        exit();
    }
    
    echo '<div class="list-group list-group-flush rounded-0">';
    foreach ($bookings as $b) {
        $status_badge = '';
        $bg_class = 'hover-bg-glass'; // Default background hover
        
        if ($b['status'] == 'pending') {
            $status_badge = '<span class="badge bg-warning text-dark"><i class="fas fa-spinner fa-spin me-1"></i> รอดำเนินการ</span>';
            if ($role === 'musician') $bg_class = 'bg-warning bg-opacity-10'; // highlight pending for musician
        } elseif ($b['status'] == 'confirmed') {
            $status_badge = '<span class="badge bg-cyan text-dark"><i class="fas fa-check-circle me-1"></i> ยืนยันแล้ว</span>';
            if ($role === 'employer') $bg_class = 'bg-cyan bg-opacity-10'; // highlight confirmed for employer
        } elseif ($b['status'] == 'rejected') {
            $status_badge = '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i> ปฏิเสธ</span>';
        } elseif ($b['status'] == 'completed') {
            $status_badge = '<span class="badge bg-success"><i class="fas fa-check-double me-1"></i> เสร็จสิ้น</span>';
        }
        
        $date_str = date('d/m/Y', strtotime($b['booking_date']));
        $time_str = date('H:i', strtotime($b['start_time'])) . ' - ' . date('H:i', strtotime($b['end_time']));
        
        echo '<a href="booking.php" class="list-group-item list-group-item-action border-secondary border-opacity-25 text-white ' . $bg_class . '" style="background: transparent;">';
        echo '<div class="d-flex w-100 justify-content-between align-items-center mb-1">';
        echo '<h6 class="mb-0 fw-bold"><i class="fas fa-user-circle text-primary me-2"></i>' . htmlspecialchars($b['other_name']) . '</h6>';
        echo '<small class="text-secondary">' . date('d/m H:i', strtotime($b['created_at'])) . '</small>';
        echo '</div>';
        echo '<div class="mb-2 text-light small"><i class="far fa-calendar-alt text-cyan me-1"></i> ' . $date_str . ' <i class="far fa-clock text-cyan ms-2 me-1"></i> ' . $time_str . '</div>';
        echo '<div class="d-flex justify-content-between align-items-center">';
        echo '<div>' . $status_badge . '</div>';
        echo '<small class="text-primary">ดูรายละเอียด <i class="fas fa-chevron-right ms-1"></i></small>';
        echo '</div>';
        echo '</a>';
    }
    echo '</div>';
    echo '<style>.hover-bg-glass:hover { background-color: rgba(255,255,255,0.05) !important; }</style>';
    
} catch (PDOException $e) {
    echo '<div class="text-center py-4 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
}
?>
