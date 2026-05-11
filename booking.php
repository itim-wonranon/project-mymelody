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

// Handle New Booking (Employer Only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_booking']) && $role === 'employer') {
    $musician_id = $_POST['musician_id'];
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
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

<div class="container py-5">
    <h2 class="fw-bold mb-4"><i class="far fa-calendar-alt text-primary me-2"></i>การนัดหมายและคิวงาน</h2>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    
    <?php if ($target_musician_id && $role === 'employer'): ?>
        <div class="card shadow-sm mb-5 border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0 fw-bold">จองคิวงาน: <?php echo htmlspecialchars($target_musician_name); ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="booking.php">
                    <input type="hidden" name="submit_booking" value="1">
                    <input type="hidden" name="musician_id" value="<?php echo $target_musician_id; ?>">
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">วันที่</label>
                            <input type="date" class="form-control" name="booking_date" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">เวลาเริ่ม</label>
                            <input type="time" class="form-control" name="start_time" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">เวลาเลิก</label>
                            <input type="time" class="form-control" name="end_time" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">รายละเอียดงาน / สถานที่ / เบอร์ติดต่อ</label>
                        <textarea class="form-control" name="details" rows="3" required placeholder="กรุณาระบุรายละเอียดให้ชัดเจน เพื่อให้นักดนตรีตัดสินใจรับงานได้ง่ายขึ้น"></textarea>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary rounded-pill px-4">ส่งคำขอจองคิว</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>วันที่</th>
                            <th>เวลา</th>
                            <th><?php echo $role === 'employer' ? 'นักดนตรี' : 'ผู้ว่าจ้าง'; ?></th>
                            <th>รายละเอียด</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bookings) > 0): ?>
                            <?php foreach ($bookings as $b): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($b['booking_date'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($b['start_time'])) . ' - ' . date('H:i', strtotime($b['end_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($b['other_name']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="popover" title="รายละเอียดงาน" data-bs-content="<?php echo htmlspecialchars($b['details']); ?>">
                                            ดูข้อมูล
                                        </button>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($b['status'] == 'pending') echo '<span class="badge bg-warning text-dark">รอดำเนินการ</span>';
                                        elseif ($b['status'] == 'confirmed') echo '<span class="badge bg-primary">ยืนยันแล้ว</span>';
                                        elseif ($b['status'] == 'rejected') echo '<span class="badge bg-danger">ปฏิเสธ</span>';
                                        elseif ($b['status'] == 'completed') echo '<span class="badge bg-success">เสร็จสิ้น</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($role === 'musician' && $b['status'] == 'pending'): ?>
                                            <form method="POST" action="booking.php" class="d-inline">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                <input type="hidden" name="status" value="confirmed">
                                                <button type="submit" class="btn btn-sm btn-success">รับงาน</button>
                                            </form>
                                            <form method="POST" action="booking.php" class="d-inline">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                <input type="hidden" name="status" value="rejected">
                                                <button type="submit" class="btn btn-sm btn-danger">ปฏิเสธ</button>
                                            </form>
                                        <?php elseif ($role === 'musician' && $b['status'] == 'confirmed'): ?>
                                            <form method="POST" action="booking.php" class="d-inline">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" class="btn btn-sm btn-success">จบงาน (Completed)</button>
                                            </form>
                                        <?php elseif ($role === 'employer' && $b['status'] == 'completed'): ?>
                                            <!-- Check if already reviewed -->
                                            <?php
                                            $stmtRev = $conn->prepare("SELECT id FROM reviews WHERE booking_id = ?");
                                            $stmtRev->execute([$b['id']]);
                                            if (!$stmtRev->fetch()):
                                            ?>
                                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $b['id']; ?>">
                                                    <i class="fas fa-star"></i> รีวิว
                                                </button>
                                                
                                                <!-- Review Modal -->
                                                <div class="modal fade" id="reviewModal<?php echo $b['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">รีวิวผลงานของ <?php echo htmlspecialchars($b['other_name']); ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST" action="booking.php">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="submit_review" value="1">
                                                                    <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                                    <input type="hidden" name="musician_id" value="<?php echo $b['musician_id']; ?>">
                                                                    
                                                                    <div class="mb-3">
                                                                        <label class="form-label">ให้คะแนน (1-5)</label>
                                                                        <select name="rating" class="form-select" required>
                                                                            <option value="5">5 ดาว - ยอดเยี่ยม</option>
                                                                            <option value="4">4 ดาว - ดีมาก</option>
                                                                            <option value="3">3 ดาว - ปานกลาง</option>
                                                                            <option value="2">2 ดาว - พอใช้</option>
                                                                            <option value="1">1 ดาว - ต้องปรับปรุง</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">ความคิดเห็น</label>
                                                                        <textarea name="comment" class="form-control" rows="3"></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="submit" class="btn btn-primary">ส่งรีวิว</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">รีวิวแล้ว</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">ไม่มีรายการนัดหมาย</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Initialize Popovers -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl)
    })
});
</script>

<?php include 'includes/footer.php'; ?>
