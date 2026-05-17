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

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    
    // Update Users Table
    $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, email=? WHERE id=?");
    if ($stmt->execute([$first_name, $last_name, $phone, $email, $user_id])) {
        $success = "อัปเดตโปรไฟล์ทั่วไปสำเร็จ";
    } else {
        $error = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล";
    }
    
    // Handle Profile Image Upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $upload_dir = 'uploads/avatars/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $filename = time() . '_' . basename($_FILES['profile_image']['name']);
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
            $table = ($role === 'musician') ? 'musician_profiles' : 'employer_profiles';
            $stmt = $conn->prepare("UPDATE $table SET profile_image=? WHERE user_id=?");
            $stmt->execute([$filename, $user_id]);
            $_SESSION['profile_image'] = $filename;
        }
    }
}

// Fetch Profile Data
$profile = [];
if ($role === 'musician') {
    $stmt = $conn->prepare("SELECT * FROM musician_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
} else if ($role === 'employer') {
    $stmt = $conn->prepare("SELECT * FROM employer_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch();

// Fetch Statistics and Recent Bookings for Dashboard
$stats = [];
$recent_bookings = [];

if ($role === 'musician') {
    // Total bookings count
    $stmt_stats = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE musician_id = ?");
    $stmt_stats->execute([$user_id]);
    $stats['total_bookings'] = $stmt_stats->fetchColumn();

    // Total portfolios count
    $stmt_stats2 = $conn->prepare("SELECT COUNT(*) FROM portfolios WHERE musician_id = ?");
    $stmt_stats2->execute([$user_id]);
    $stats['total_portfolios'] = $stmt_stats2->fetchColumn();
    
    // Average rating
    $stats['avg_rating'] = !empty($profile['rating_score']) ? number_format($profile['rating_score'], 1) : '5.0';

    // Recent 3 bookings
    $stmt_recent = $conn->prepare("
        SELECT b.*, u.username as other_name 
        FROM bookings b 
        JOIN users u ON b.employer_id = u.id 
        WHERE b.musician_id = ? 
        ORDER BY b.created_at DESC 
        LIMIT 3
    ");
    $stmt_recent->execute([$user_id]);
    $recent_bookings = $stmt_recent->fetchAll();
} else if ($role === 'employer') {
    // Total booked musicians count
    $stmt_stats = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE employer_id = ?");
    $stmt_stats->execute([$user_id]);
    $stats['total_bookings'] = $stmt_stats->fetchColumn();

    // Total reviews written count
    $stmt_stats2 = $conn->prepare("SELECT COUNT(*) FROM reviews WHERE employer_id = ?");
    $stmt_stats2->execute([$user_id]);
    $stats['total_reviews'] = $stmt_stats2->fetchColumn();

    // Active confirmed bookings
    $stmt_stats3 = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE employer_id = ? AND status = 'confirmed'");
    $stmt_stats3->execute([$user_id]);
    $stats['active_bookings'] = $stmt_stats3->fetchColumn();

    // Recent 3 bookings
    $stmt_recent = $conn->prepare("
        SELECT b.*, u.username as other_name 
        FROM bookings b 
        JOIN users u ON b.musician_id = u.id 
        WHERE b.employer_id = ? 
        ORDER BY b.created_at DESC 
        LIMIT 3
    ");
    $stmt_recent->execute([$user_id]);
    $recent_bookings = $stmt_recent->fetchAll();
}
?>
<?php include 'includes/header.php'; ?>

<div class="container py-5 animate__animated animate__fadeIn">
    <!-- 1. Premium Dashboard Header -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold text-white mb-1" style="font-size: 2.25rem; text-shadow: 0 0 25px rgba(196, 113, 237, 0.35);">
                <i class="fas fa-columns text-primary me-2"></i>แผงควบคุมและโปรไฟล์
            </h2>
            <p class="text-secondary mb-0">แผงควบคุมสถิติภาพรวมและการจัดการข้อมูลสมาชิกของ My Melody</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <span class="badge bg-dark bg-opacity-50 border border-secondary border-opacity-15 px-3 py-2 rounded-pill text-light">
                <i class="far fa-calendar-alt me-1 text-cyan"></i> <?php echo date('d F Y'); ?>
            </span>
        </div>
    </div>

    <!-- 2. Dynamic Statistics Row -->
    <div class="row mb-4">
        <?php if ($role === 'musician'): ?>
            <!-- Stat 1: Bookings -->
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card dashboard-stat-card p-4 h-100">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                           <span class="text-secondary small fw-bold d-block mb-1 text-uppercase tracking-wider">ยอดจองคิวงานทั้งหมด</span>
                           <h2 class="fw-extrabold text-white mb-0" style="font-size: 2.2rem; text-shadow: 0 0 10px rgba(0, 240, 255, 0.4);">
                               <?php echo number_format($stats['total_bookings']); ?> <span class="fs-6 fw-normal text-secondary">ครั้ง</span>
                           </h2>
                        </div>
                        <div class="rounded-4 p-3 text-cyan" style="background: rgba(0, 240, 255, 0.08);">
                           <i class="far fa-calendar-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Stat 2: Rating -->
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card dashboard-stat-card p-4 h-100">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                           <span class="text-secondary small fw-bold d-block mb-1 text-uppercase tracking-wider">คะแนนรีวิวเฉลี่ย</span>
                           <h2 class="fw-extrabold text-white mb-0" style="font-size: 2.2rem; text-shadow: 0 0 10px rgba(241, 196, 15, 0.4);">
                               <?php echo $stats['avg_rating']; ?> <span class="fs-6 fw-normal text-secondary">/ 5.0</span>
                           </h2>
                        </div>
                        <div class="rounded-4 p-3 text-warning" style="background: rgba(241, 196, 15, 0.08);">
                           <i class="fas fa-star fa-2x" style="color: #f1c40f; filter: drop-shadow(0 0 5px rgba(241, 196, 15, 0.5));"></i>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Stat 3: Portfolios -->
            <div class="col-md-4">
                <div class="card dashboard-stat-card p-4 h-100">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                           <span class="text-secondary small fw-bold d-block mb-1 text-uppercase tracking-wider">คลังผลงานทั้งหมด</span>
                           <h2 class="fw-extrabold text-white mb-0" style="font-size: 2.2rem; text-shadow: 0 0 10px rgba(255, 142, 251, 0.4);">
                               <?php echo number_format($stats['total_portfolios']); ?> <span class="fs-6 fw-normal text-secondary">รายการ</span>
                           </h2>
                        </div>
                        <div class="rounded-4 p-3 text-pink" style="background: rgba(255, 142, 251, 0.08);">
                           <i class="fas fa-photo-video fa-2x" style="color: var(--accent-color);"></i>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Stat 1: Hired Count -->
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card dashboard-stat-card p-4 h-100">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                           <span class="text-secondary small fw-bold d-block mb-1 text-uppercase tracking-wider">จำนวนจองนักดนตรี</span>
                           <h2 class="fw-extrabold text-white mb-0" style="font-size: 2.2rem; text-shadow: 0 0 10px rgba(0, 240, 255, 0.4);">
                               <?php echo number_format($stats['total_bookings']); ?> <span class="fs-6 fw-normal text-secondary">ครั้ง</span>
                           </h2>
                        </div>
                        <div class="rounded-4 p-3 text-cyan" style="background: rgba(0, 240, 255, 0.08);">
                           <i class="fas fa-user-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Stat 2: Reviews -->
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card dashboard-stat-card p-4 h-100">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                           <span class="text-secondary small fw-bold d-block mb-1 text-uppercase tracking-wider">รีวิวที่เคยเขียน</span>
                           <h2 class="fw-extrabold text-white mb-0" style="font-size: 2.2rem; text-shadow: 0 0 10px rgba(241, 196, 15, 0.4);">
                               <?php echo number_format($stats['total_reviews']); ?> <span class="fs-6 fw-normal text-secondary">รีวิว</span>
                           </h2>
                        </div>
                        <div class="rounded-4 p-3 text-warning" style="background: rgba(241, 196, 15, 0.08);">
                           <i class="fas fa-star fa-2x" style="color: #f1c40f;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Stat 3: Active Bookings -->
            <div class="col-md-4">
                <div class="card dashboard-stat-card p-4 h-100">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                           <span class="text-secondary small fw-bold d-block mb-1 text-uppercase tracking-wider">คิวงานรอแสดงผล</span>
                           <h2 class="fw-extrabold text-white mb-0" style="font-size: 2.2rem; text-shadow: 0 0 10px rgba(46, 213, 115, 0.4);">
                               <?php echo number_format($stats['active_bookings']); ?> <span class="fs-6 fw-normal text-secondary">งาน</span>
                           </h2>
                        </div>
                        <div class="rounded-4 p-3 text-success" style="background: rgba(46, 213, 115, 0.08);">
                           <i class="fas fa-clipboard-check fa-2x" style="color: #2ed573;"></i>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- 3. Sidebar + Main Profile Content -->
    <div class="row">
        <!-- Sidebar Profile Card -->
        <div class="col-lg-4 mb-4">
            <!-- 3a. Profile Card -->
            <div class="card glass-card-premium text-center p-4 mb-4">
                <div class="card-body p-0">
                    <?php 
                    $img_src = !empty($profile['profile_image']) && $profile['profile_image'] !== 'default_avatar.png' ? 'uploads/avatars/' . $profile['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($user_info['username']).'&background=0D8ABC&color=fff';
                    ?>
                    
                    <!-- Floating Avatar Showcase with Glow Ring -->
                    <div class="premium-avatar-wrapper mx-auto mb-3" style="width: 120px; height: 120px;">
                        <div class="premium-avatar-glow-ring" style="box-shadow: 0 0 15px rgba(196, 113, 237, 0.35);"></div>
                        <img src="<?php echo htmlspecialchars($img_src); ?>" class="premium-avatar-image" alt="Profile" style="border-width: 4px;">
                    </div>

                    <h4 class="card-title fw-bold text-white mb-1"><?php echo htmlspecialchars($user_info['username']); ?></h4>
                    <p class="text-secondary small mb-3"><?php echo htmlspecialchars($user_info['email']); ?></p>
                    
                    <span class="badge bg-dark bg-opacity-70 text-<?php echo $role === 'musician' ? 'info border-info' : 'success border-success'; ?> border px-3 py-2 rounded-pill shadow-sm" style="font-size: 0.85rem;">
                        <?php echo $role === 'musician' ? '<i class="fas fa-guitar me-1"></i> นักดนตรี' : '<i class="fas fa-briefcase me-1"></i> ผู้ว่าจ้าง'; ?>
                    </span>
                    
                    <?php if ($role === 'musician' && $profile['is_verified']): ?>
                        <div class="mt-3 text-success fw-bold small"><i class="fas fa-check-circle me-1"></i> ยืนยันตัวตนแล้ว</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 3b. Quick Action Cyber-Deck -->
            <div class="card glass-card-premium p-4">
                <h5 class="fw-bold text-white mb-3 pb-2 border-bottom border-secondary border-opacity-15">
                    <i class="fas fa-bullseye text-pink me-2"></i>เมนูจัดการระบบ
                </h5>
                <div class="d-flex flex-column gap-3">
                    <?php if ($role === 'musician'): ?>
                        <a href="edit_musician.php" class="btn btn-cyber-outline btn-cyber-outline-pink w-100 py-2.5 text-start px-3">
                            <i class="fas fa-sliders-h me-2"></i>แก้ไขข้อมูลเฉพาะทาง
                        </a>
                        <a href="portfolio_manager.php" class="btn btn-cyber-outline btn-cyber-outline-pink w-100 py-2.5 text-start px-3">
                            <i class="fas fa-photo-video me-2"></i>จัดการคลังผลงาน (Portfolio)
                        </a>
                        <a href="musician_profile.php?musician_id=<?php echo $user_id; ?>" class="btn btn-cyber-outline btn-cyber-outline-cyan w-100 py-2.5 text-start px-3">
                            <i class="fas fa-external-link-alt me-2"></i>ดูหน้าโปรไฟล์สาธารณะ
                        </a>
                    <?php else: ?>
                        <a href="search.php" class="btn btn-cyber-outline btn-cyber-outline-cyan w-100 py-2.5 text-start px-3">
                            <i class="fas fa-search me-2"></i>ค้นหาศิลปินนักดนตรี
                        </a>
                        <a href="booking.php" class="btn btn-cyber-outline btn-cyber-outline-pink w-100 py-2.5 text-start px-3">
                            <i class="far fa-calendar-alt me-2"></i>จัดการคิวงานการจอง
                        </a>
                        <a href="community.php" class="btn btn-cyber-outline btn-cyber-outline-warning w-100 py-2.5 text-start px-3">
                            <i class="fas fa-users me-2"></i>คอมมูนิตี้และฟีดข่าว
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Profile Content Card -->
        <div class="col-lg-8">
            <!-- 3c. Edit profile form -->
            <div class="card glass-card-premium p-4 mb-4">
                <div class="d-flex align-items-center justify-content-between mb-4 border-bottom border-secondary border-opacity-15 pb-3">
                    <h4 class="fw-bold mb-0 text-gradient"><i class="fas fa-user-edit me-2"></i>แก้ไขข้อมูลส่วนตัว</h4>
                    <span class="text-secondary small">MY MELODY PROFILE</span>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible border-0 bg-success bg-opacity-15 text-success rounded-4 fade show px-4 py-3 mb-4">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close text-success" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible border-0 bg-danger bg-opacity-15 text-danger rounded-4 fade show px-4 py-3 mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close text-danger" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="profile.php" enctype="multipart/form-data">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <!-- Premium Interactive Avatar Editor -->
                    <div class="avatar-editor-wrapper mx-auto mb-3" style="width: 100px; height: 100px;">
                        <img id="avatarPreviewImg" src="<?php echo htmlspecialchars($img_src); ?>" class="avatar-editor-img" alt="Avatar Preview" style="width: 100px; height: 100px;">
                        <div id="avatarOverlayBtn" class="avatar-editor-overlay">
                            <i class="fas fa-pencil-alt"></i>
                        </div>
                        <input type="file" id="avatarFileInput" class="avatar-editor-file-input" name="profile_image" accept="image/*">
                    </div>
                    <div class="text-center text-secondary small mb-4">คลิกที่รูปดินสอเพื่อเปลี่ยนรูปโปรไฟล์ของคุณ</div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label text-light fw-bold" style="font-size: 0.9rem;">ชื่อจริง</label>
                            <div class="form-input-icon-wrapper">
                                <i class="fas fa-user text-cyan"></i>
                                <input type="text" class="form-control form-control-premium text-white bg-transparent border-0" name="first_name" value="<?php echo htmlspecialchars($user_info['first_name'] ?? ''); ?>" placeholder="กรอกชื่อจริง" style="padding-left: 2.75rem;" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label text-light fw-bold" style="font-size: 0.9rem;">นามสกุล</label>
                            <div class="form-input-icon-wrapper">
                                <i class="fas fa-user text-cyan"></i>
                                <input type="text" class="form-control form-control-premium text-white bg-transparent border-0" name="last_name" value="<?php echo htmlspecialchars($user_info['last_name'] ?? ''); ?>" placeholder="กรอกนามสกุล" style="padding-left: 2.75rem;" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-secondary fw-bold" style="font-size: 0.9rem;">ชื่อผู้ใช้งาน (Username) <span class="badge bg-secondary rounded-pill ms-2" style="font-size: 0.65rem;">ห้ามแก้ไข</span></label>
                        <div class="form-input-icon-wrapper">
                            <i class="fas fa-lock opacity-50 text-secondary"></i>
                            <input type="text" class="form-control form-control-premium bg-transparent border-0" style="opacity: 0.6; cursor: not-allowed; padding-left: 2.75rem;" value="<?php echo htmlspecialchars($user_info['username'] ?? ''); ?>" disabled>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label text-light fw-bold" style="font-size: 0.9rem;">อีเมลสำหรับติดต่อ</label>
                            <div class="form-input-icon-wrapper">
                                <i class="fas fa-envelope text-cyan"></i>
                                <input type="email" class="form-control form-control-premium text-white bg-transparent border-0" name="email" value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" placeholder="example@mail.com" style="padding-left: 2.75rem;" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label text-light fw-bold" style="font-size: 0.9rem;">เบอร์โทรศัพท์ติดต่อ</label>
                            <div class="form-input-icon-wrapper">
                                <i class="fas fa-phone text-cyan"></i>
                                <input type="tel" class="form-control form-control-premium text-white bg-transparent border-0" name="phone" value="<?php echo htmlspecialchars($user_info['phone'] ?? ''); ?>" placeholder="08XXXXXXXX" style="padding-left: 2.75rem;" required>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-2">
                        <button type="submit" class="btn btn-primary rounded-pill px-5 py-2.5 glow-btn shadow-lg" style="color: #3b0059; font-weight: bold;">
                            <i class="fas fa-save me-2"></i>บันทึกข้อมูลส่วนตัว
                        </button>
                    </div>
                </form>
            </div>

            <!-- 3d. Recent Bookings Summary Card (Breathtaking Dynamic Feature) -->
            <div class="card glass-card-premium p-4">
                <div class="d-flex align-items-center justify-content-between mb-4 border-bottom border-secondary border-opacity-15 pb-3">
                    <h5 class="fw-bold mb-0 text-white"><i class="far fa-calendar-check me-2 text-cyan"></i>รายการนัดหมายล่าสุด</h5>
                    <a href="booking.php" class="text-decoration-none small text-cyan hover-glow">ดูทั้งหมด <i class="fas fa-chevron-right ms-1"></i></a>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle text-white mb-0" style="border-collapse: separate; border-spacing: 0 6px;">
                        <thead>
                            <tr class="text-secondary small" style="border: none;">
                                <th style="border: none;">วันที่</th>
                                <th style="border: none;"><?php echo $role === 'employer' ? 'นักดนตรี' : 'ผู้ว่าจ้าง'; ?></th>
                                <th style="border: none;">เวลา</th>
                                <th style="border: none;">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recent_bookings) > 0): ?>
                                <?php foreach ($recent_bookings as $b): ?>
                                    <tr style="background: rgba(255, 255, 255, 0.01); border-radius: 8px;">
                                        <td class="small py-2.5"><?php echo date('d/m/Y', strtotime($b['booking_date'])); ?></td>
                                        <td class="small py-2.5 fw-bold text-light"><?php echo htmlspecialchars($b['other_name']); ?></td>
                                        <td class="small py-2.5 text-secondary"><?php echo date('H:i', strtotime($b['start_time'])) . '-' . date('H:i', strtotime($b['end_time'])) . ' น.'; ?></td>
                                        <td class="small py-2.5">
                                            <?php 
                                            if ($b['status'] == 'pending') echo '<span class="booking-neon-badge badge-neon-warning">รอดำเนินการ</span>';
                                            elseif ($b['status'] == 'confirmed') echo '<span class="booking-neon-badge badge-neon-cyan">ยืนยันแล้ว</span>';
                                            elseif ($b['status'] == 'rejected') echo '<span class="booking-neon-badge badge-neon-danger">ปฏิเสธ</span>';
                                            elseif ($b['status'] == 'completed') echo '<span class="booking-neon-badge badge-neon-success">เสร็จสิ้น</span>';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-3 text-secondary small">ไม่มีรายการจองคิวงานในปัจจุบัน</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Avatar editor click triggers
document.getElementById('avatarOverlayBtn').addEventListener('click', function() {
    document.getElementById('avatarFileInput').click();
});

// Live avatar change preview
document.getElementById('avatarFileInput').addEventListener('change', function() {
    if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreviewImg').src = e.target.result;
        }
        reader.readAsDataURL(this.files[0]);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
