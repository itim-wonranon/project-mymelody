<?php
require_once 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$success = '';
$error = '';

function clean_thai_time($time_str) {
    $time_str = trim($time_str);
    // Remove Thai time suffix and spaces
    $time_str = str_ireplace(['น.', 'น', ' '], '', $time_str);
    // Replace dots with colons
    $time_str = str_replace('.', ':', $time_str);
    
    // HH:MM format
    if (preg_match('/^([0-9]{1,2}):([0-9]{2})$/', $time_str, $matches)) {
        return sprintf('%02d:%02d:00', $matches[1], $matches[2]);
    }
    // HH:MM:SS format
    if (preg_match('/^([0-9]{1,2}):([0-9]{2}):([0-9]{2})$/', $time_str, $matches)) {
        return sprintf('%02d:%02d:%02d', $matches[1], $matches[2], $matches[3]);
    }
    // Hour only (e.g. 18 -> 18:00:00)
    if (preg_match('/^([0-9]{1,2})$/', $time_str, $matches)) {
        return sprintf('%02d:00:00', $matches[1]);
    }
    return $time_str;
}

// Handle New Booking (Employer Only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_booking']) && $role === 'employer') {
    $musician_id = $_POST['musician_id'];
    $booking_date = $_POST['booking_date'];
    $start_time = clean_thai_time($_POST['start_time']);
    $end_time = clean_thai_time($_POST['end_time']);
    $details = $_POST['details'];

    $stmt = $conn->prepare("INSERT INTO bookings (employer_id, musician_id, booking_date, start_time, end_time, details) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$user_id, $musician_id, $booking_date, $start_time, $end_time, $details])) {
        $success = "ส่งคำขอจองคิวงานเรียบร้อยแล้ว กรุณารอนักดนตรียืนยัน";
    } else {
        $error = "เกิดข้อผิดพลาดในการจองคิวงาน";
    }
}

// Handle Status Update (Musician Only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status']) && $role === 'musician') {
    $booking_id = $_POST['booking_id'];
    $status = $_POST['status']; // confirmed, rejected, completed

    $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ? AND musician_id = ?");
    if ($stmt->execute([$status, $booking_id, $user_id])) {
        $success = "อัปเดตสถานะงานเรียบร้อย";
    }
}

// Handle Review (Employer Only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review']) && $role === 'employer') {
    $booking_id = $_POST['booking_id'];
    $musician_id = $_POST['musician_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];

    $stmt = $conn->prepare("INSERT INTO reviews (booking_id, employer_id, musician_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$booking_id, $user_id, $musician_id, $rating, $comment])) {
        // Update Musician Rating
        $stmt2 = $conn->prepare("SELECT AVG(rating) as avg_rating FROM reviews WHERE musician_id = ?");
        $stmt2->execute([$musician_id]);
        $avg = $stmt2->fetch()['avg_rating'];
        
        $stmt3 = $conn->prepare("UPDATE musician_profiles SET rating_score = ? WHERE user_id = ?");
        $stmt3->execute([$avg, $musician_id]);
        
        $success = "บันทึกรีวิวเรียบร้อย ขอบคุณครับ";
    }
}

// Fetch Bookings
$bookings = [];
if ($role === 'employer') {
    $stmt = $conn->prepare("
        SELECT b.*, u.username as other_name 
        FROM bookings b 
        JOIN users u ON b.musician_id = u.id 
        WHERE b.employer_id = ? 
        ORDER BY b.created_at DESC
    ");
} else if ($role === 'musician') {
    $stmt = $conn->prepare("
        SELECT b.*, u.username as other_name 
        FROM bookings b 
        JOIN users u ON b.employer_id = u.id 
        WHERE b.musician_id = ? 
        ORDER BY b.created_at DESC
    ");
} else {
    // Admin sees nothing here
    $stmt = $conn->prepare("SELECT 1 FROM users WHERE 0=1"); 
}
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();

// If coming from profile page to book
$target_musician_id = $_GET['musician_id'] ?? null;
$target_musician_name = '';
if ($target_musician_id && $role === 'employer') {
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$target_musician_id]);
    $target_musician_name = $stmt->fetchColumn();
}

?>
<?php include 'includes/header.php'; ?>

<div class="container py-5 animate__animated animate__fadeIn">
    <!-- 1. Premium Dashboard Header -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold text-white mb-1" style="font-size: 2.25rem; text-shadow: 0 0 25px rgba(196, 113, 237, 0.35);">
                <i class="far fa-calendar-alt text-primary me-2"></i>การนัดหมายและคิวงาน
            </h2>
            <p class="text-secondary mb-0">จัดการคิวแสดงดนตรี ประวัติการจอง และการตอบรับจ้างงานสำหรับนักดนตรีและผู้จ้างงาน</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <span class="badge bg-dark bg-opacity-50 border border-secondary border-opacity-15 px-3 py-2 rounded-pill text-light" style="font-size: 0.85rem;">
                <i class="fas fa-user-circle me-1 text-cyan"></i> บทบาท: <?php echo $role === 'musician' ? 'นักดนตรี' : 'ผู้ว่าจ้าง'; ?>
            </span>
        </div>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible border-0 bg-success bg-opacity-15 text-success rounded-4 fade show px-4 py-3 mb-4 animate__animated animate__fadeIn">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close text-success" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible border-0 bg-danger bg-opacity-15 text-danger rounded-4 fade show px-4 py-3 mb-4 animate__animated animate__fadeIn">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close text-danger" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- 2. Add Booking Request Form Card (Employer Only) -->
    <?php if ($target_musician_id && $role === 'employer'): ?>
        <div class="card glass-card-premium border-primary mb-5 animate__animated animate__fadeIn">
            <div class="card-header bg-transparent border-bottom border-secondary border-opacity-15 py-3">
                <h5 class="mb-0 fw-bold text-white"><i class="fas fa-file-signature text-pink me-2"></i>จองคิวงานแสดงดนตรี: <?php echo htmlspecialchars($target_musician_name); ?></h5>
            </div>
            <div class="card-body py-4">
                <form method="POST" action="booking.php">
                    <input type="hidden" name="submit_booking" value="1">
                    <input type="hidden" name="musician_id" value="<?php echo $target_musician_id; ?>">
                    
                    <div class="row">
                        <!-- Date Picker -->
                        <div class="col-md-4 mb-4">
                            <label class="form-label text-light fw-bold" style="font-size: 0.9rem;">วันที่จองแสดง</label>
                            <div class="form-input-icon-wrapper">
                                <i class="far fa-calendar-alt text-cyan"></i>
                                <input type="date" class="form-control form-control-premium text-white bg-transparent border-0" name="booking_date" style="padding-left: 2.75rem;" required>
                            </div>
                        </div>
                        <!-- Start Time -->
                        <div class="col-md-4 mb-4">
                            <label class="form-label text-light fw-bold" style="font-size: 0.9rem;">เวลาเริ่มแสดง</label>
                            <div class="form-input-icon-wrapper">
                                <i class="far fa-clock text-cyan"></i>
                                <input type="text" class="form-control form-control-premium text-white bg-transparent border-0" name="start_time" placeholder="เช่น 18:00 หรือ 18.30" style="padding-left: 2.75rem;" required>
                            </div>
                        </div>
                        <!-- End Time -->
                        <div class="col-md-4 mb-4">
                            <label class="form-label text-light fw-bold" style="font-size: 0.9rem;">เวลาเลิกงาน</label>
                            <div class="form-input-icon-wrapper">
                                <i class="far fa-clock text-cyan"></i>
                                <input type="text" class="form-control form-control-premium text-white bg-transparent border-0" name="end_time" placeholder="เช่น 21:00 หรือ 21.00" style="padding-left: 2.75rem;" required>
                            </div>
                        </div>
                    </div>
                    <!-- Details -->
                    <div class="mb-4">
                        <label class="form-label text-light fw-bold" style="font-size: 0.9rem;">รายละเอียดงานแสดง / สถานที่จัดงาน / เบอร์โทรศัพท์ติดต่อ</label>
                        <textarea class="form-control form-control-premium text-white bg-transparent border-secondary" name="details" rows="3" style="border-radius: 12px; padding: 12px;" required placeholder="กรุณาระบุรายละเอียดให้ชัดเจน (เช่น ชื่องาน, สถานที่, สไตล์เพลงที่ต้องการ, เบอร์ติดต่อกลับ) เพื่อให้นักดนตรีพิจารณาและรับงานได้รวดเร็วขึ้น"></textarea>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary rounded-pill px-5 py-2.5 glow-btn shadow-lg" style="color: #3b0059; font-weight: bold;">
                            <i class="fas fa-paper-plane me-2"></i>ส่งคำขอจองคิวงาน
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- 3. Glassmorphic Bookings Queue Grid -->
    <div class="card glass-card-premium p-4 border-0 shadow-lg">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table-glass-premium">
                    <thead>
                        <tr>
                            <th>วันที่แสดง</th>
                            <th>ช่วงเวลา</th>
                            <th><?php echo $role === 'employer' ? 'ศิลปินนักดนตรี' : 'ผู้ว่าจ้าง'; ?></th>
                            <th>รายละเอียดงาน</th>
                            <th>สถานะคิวงาน</th>
                            <th class="text-end">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bookings) > 0): ?>
                            <?php foreach ($bookings as $b): ?>
                                <tr class="animate__animated animate__fadeIn">
                                    <td class="fw-bold text-light">
                                        <i class="far fa-calendar-alt text-cyan me-2"></i><?php echo date('d/m/Y', strtotime($b['booking_date'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark bg-opacity-40 text-secondary border border-secondary border-opacity-15 px-3 py-2 rounded-pill" style="font-size: 0.8rem;">
                                            <i class="far fa-clock text-cyan me-2"></i><?php echo date('H:i', strtotime($b['start_time'])) . ' - ' . date('H:i', strtotime($b['end_time'])) . ' น.'; ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold">
                                        <i class="fas fa-user-circle text-pink me-2"></i><?php echo htmlspecialchars($b['other_name']); ?>
                                    </td>
                                    <td>
                                        <!-- Details Modal Trigger -->
                                        <button type="button" class="btn btn-sm btn-cyber-outline btn-cyber-outline-cyan px-3 py-2" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $b['id']; ?>">
                                            <i class="fas fa-search me-2"></i>ดูข้อมูลงาน
                                        </button>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($b['status'] == 'pending') echo '<span class="booking-neon-badge badge-neon-warning"><i class="fas fa-spinner fa-spin me-2"></i>รอดำเนินการ</span>';
                                        elseif ($b['status'] == 'confirmed') echo '<span class="booking-neon-badge badge-neon-cyan"><i class="fas fa-check-circle me-2"></i>ยืนยันแล้ว</span>';
                                        elseif ($b['status'] == 'rejected') echo '<span class="booking-neon-badge badge-neon-danger"><i class="fas fa-times-circle me-2"></i>ปฏิเสธ</span>';
                                        elseif ($b['status'] == 'completed') echo '<span class="booking-neon-badge badge-neon-success"><i class="fas fa-check-double me-2"></i>เสร็จสิ้น</span>';
                                        ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($role === 'musician' && $b['status'] == 'pending'): ?>
                                            <div class="d-inline-flex gap-2">
                                                <form method="POST" action="booking.php" class="d-inline">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                    <input type="hidden" name="status" value="confirmed">
                                                    <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 py-2"><i class="fas fa-check me-2"></i>รับงาน</button>
                                                </form>
                                                <form method="POST" action="booking.php" class="d-inline">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                    <input type="hidden" name="status" value="rejected">
                                                    <button type="submit" class="btn btn-sm btn-danger rounded-pill px-3 py-2"><i class="fas fa-times me-2"></i>ปฏิเสธ</button>
                                                </form>
                                            </div>
                                        <?php elseif ($role === 'musician' && $b['status'] == 'confirmed'): ?>
                                            <form method="POST" action="booking.php" class="d-inline">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 py-2"><i class="fas fa-check-double me-2"></i>จบงานสำเร็จ</button>
                                            </form>
                                        <?php elseif ($role === 'employer' && $b['status'] == 'completed'): ?>
                                            <!-- Check if already reviewed -->
                                            <?php
                                            $stmtRev = $conn->prepare("SELECT id FROM reviews WHERE booking_id = ?");
                                            $stmtRev->execute([$b['id']]);
                                            if (!$stmtRev->fetch()):
                                            ?>
                                                <button type="button" class="btn btn-sm btn-cyber-outline btn-cyber-outline-warning px-3 py-2" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $b['id']; ?>">
                                                    <i class="fas fa-star me-2 text-warning"></i> เขียนรีวิว
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-dark bg-opacity-50 text-success border border-success border-opacity-30 rounded-pill px-3 py-2 small fw-bold">
                                                    <i class="fas fa-check-circle text-success me-2"></i> รีวิวเรียบร้อย
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-secondary small">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-secondary">
                                    <i class="far fa-folder-open fa-3x mb-3 d-block text-opacity-30 text-light" style="opacity: 0.35;"></i>
                                    <span>ไม่มีรายการคิวแสดงและการนัดหมายในคลังขณะนี้</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 4. Modals Container (Rendered at root level to prevent clipping and backdrop layering bugs) -->
<?php if (count($bookings) > 0): ?>
    <?php foreach ($bookings as $b): ?>
        <!-- Details Modal -->
        <div class="modal fade" id="detailsModal<?php echo $b['id']; ?>" tabindex="-1" aria-labelledby="detailsModalLabel<?php echo $b['id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content glass-card-premium border border-secondary border-opacity-15 rounded-4 p-3 shadow-lg">
                    <div class="modal-header border-bottom border-secondary border-opacity-15">
                        <h5 class="modal-title text-white fw-bold" id="detailsModalLabel<?php echo $b['id']; ?>">
                            <i class="fas fa-info-circle text-cyan me-2"></i>รายละเอียดคิวงาน
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-start text-light py-4">
                        <div class="mb-4">
                            <strong class="text-cyan small fw-bold d-block mb-2">วันและเวลาจ้างงาน:</strong>
                            <div class="bg-dark bg-opacity-40 p-3 rounded-3 border border-secondary border-opacity-10 text-white">
                                📅 วันที่ <?php echo date('d/m/Y', strtotime($b['booking_date'])); ?> เวลา <?php echo date('H:i', strtotime($b['start_time'])) . ' - ' . date('H:i', strtotime($b['end_time'])); ?> น.
                            </div>
                        </div>
                        <div>
                            <strong class="text-cyan small fw-bold d-block mb-2">รายละเอียดและสถานที่จัดงาน:</strong>
                            <div class="bg-dark bg-opacity-40 p-3 rounded-3 border border-secondary border-opacity-10 text-white" style="white-space: pre-wrap; min-height: 80px; line-height: 1.6;">
                                <?php echo nl2br(htmlspecialchars($b['details'])); ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-secondary border-opacity-15 pt-3">
                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Review Modal -->
        <?php if ($role === 'employer' && $b['status'] == 'completed'): ?>
            <?php
            $stmtRev = $conn->prepare("SELECT id FROM reviews WHERE booking_id = ?");
            $stmtRev->execute([$b['id']]);
            if (!$stmtRev->fetch()):
            ?>
                <div class="modal fade" id="reviewModal<?php echo $b['id']; ?>" tabindex="-1" aria-labelledby="reviewModalLabel<?php echo $b['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content glass-card-premium border border-secondary border-opacity-15 rounded-4 p-3 shadow-lg">
                            <div class="modal-header border-bottom border-secondary border-opacity-15">
                                <h5 class="modal-title text-white fw-bold" id="reviewModalLabel<?php echo $b['id']; ?>">
                                    <i class="fas fa-star text-warning me-2"></i>รีวิวผลงานของ: <?php echo htmlspecialchars($b['other_name']); ?>
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="booking.php">
                                <div class="modal-body text-start py-4">
                                    <input type="hidden" name="submit_review" value="1">
                                    <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                    <input type="hidden" name="musician_id" value="<?php echo $b['musician_id']; ?>">
                                    
                                    <div class="mb-4">
                                        <label class="form-label text-light fw-bold mb-2" style="font-size: 0.9rem;">ให้คะแนนผลงานแสดงดนตรี</label>
                                        <div class="form-input-icon-wrapper">
                                            <i class="fas fa-star text-warning"></i>
                                            <select name="rating" class="form-select form-control-premium text-white bg-transparent border-0" style="padding-left: 2.75rem;" required>
                                                <option value="5" class="bg-dark text-white">⭐⭐⭐⭐⭐ (5 ดาว - ยอดเยี่ยมที่สุด)</option>
                                                <option value="4" class="bg-dark text-white">⭐⭐⭐⭐ (4 ดาว - ดีเยี่ยม)</option>
                                                <option value="3" class="bg-dark text-white">⭐⭐⭐ (3 ดาว - ดีปานกลาง)</option>
                                                <option value="2" class="bg-dark text-white">⭐⭐ (2 ดาว - พอใช้ได้)</option>
                                                <option value="1" class="bg-dark text-white">⭐ (1 ดาว - ควรปรับปรุง)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label text-light fw-bold mb-2" style="font-size: 0.9rem;">เขียนความคิดเห็นรีวิวเพิ่มเติม</label>
                                        <textarea name="comment" class="form-control form-control-premium text-white bg-transparent border-secondary" rows="3" style="border-radius: 12px; padding: 12px; line-height: 1.5;" placeholder="เขียนรีวิวเพื่อเป็นประโยชน์แก่ผู้ใช้อื่น..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer border-top border-secondary border-opacity-15 pt-3">
                                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" style="color: #3b0059;">ส่งคะแนนรีวิว</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
