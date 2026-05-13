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
?>
<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <div class="row">
        <!-- Sidebar Profile -->
        <div class="col-md-4 mb-4">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <?php 
                    $img_src = !empty($profile['profile_image']) && $profile['profile_image'] !== 'default_avatar.png' ? 'uploads/avatars/' . $profile['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($user_info['username']).'&background=0D8ABC&color=fff';
                    ?>
                    <img src="<?php echo htmlspecialchars($img_src); ?>" class="profile-img mb-3" alt="Profile">
                    <h4 class="card-title fw-bold"><?php echo htmlspecialchars($user_info['username']); ?></h4>
                    <p class="text-muted mb-1"><?php echo htmlspecialchars($user_info['email']); ?></p>
                    <span class="badge bg-<?php echo $role === 'musician' ? 'primary' : 'success'; ?> px-3 py-2 rounded-pill">
                        <?php echo $role === 'musician' ? '<i class="fas fa-guitar"></i> นักดนตรี' : '<i class="fas fa-briefcase"></i> ผู้ว่าจ้าง'; ?>
                    </span>
                    
                    <?php if ($role === 'musician' && $profile['is_verified']): ?>
                        <div class="mt-2 text-success fw-bold"><i class="fas fa-check-circle"></i> ยืนยันตัวตนแล้ว</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Profile Content -->
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h5 class="fw-bold"><i class="fas fa-user-edit text-primary me-2"></i>แก้ไขโปรไฟล์</h5>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>

                    <form method="POST" action="profile.php" enctype="multipart/form-data">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">รูปโปรไฟล์</label>
                            <input type="file" class="form-control" name="profile_image" accept="image/*">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ชื่อจริง</label>
                                <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($user_info['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">นามสกุล</label>
                                <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($user_info['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ชื่อผู้ใช้งาน (Username) - เปลี่ยนไม่ได้</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_info['username'] ?? ''); ?>" disabled>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">อีเมล</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เบอร์โทรศัพท์</label>
                                <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user_info['phone'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-save me-2"></i>บันทึกข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Portfolio Section for Musicians -->
            <?php if ($role === 'musician'): ?>
                <div class="mt-4 text-center">
                    <a href="edit_musician.php" class="btn btn-outline-info rounded-pill px-4 me-2"><i class="fas fa-guitar me-2"></i>แก้ไขข้อมูลศิลปินของคุณ</a>
                    <a href="portfolio_manager.php" class="btn btn-outline-warning rounded-pill px-4"><i class="fas fa-photo-video me-2"></i>จัดการผลงาน (Portfolio)</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
