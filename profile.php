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
    if ($role === 'musician') {
        $bio = $_POST['bio'] ?? '';
        $band_type = $_POST['band_type'] ?? 'solo';
        $genres = $_POST['genres'] ?? '';
        $rate = $_POST['rate'] ?? '';
        $location = $_POST['location'] ?? '';
        $availability_info = $_POST['availability_info'] ?? '';

        $stmt = $conn->prepare("UPDATE musician_profiles SET bio=?, band_type=?, genres=?, rate=?, location=?, availability_info=? WHERE user_id=?");
        if ($stmt->execute([$bio, $band_type, $genres, $rate, $location, $availability_info, $user_id])) {
            $success = "อัปเดตโปรไฟล์สำเร็จ";
        } else {
            $error = "เกิดข้อผิดพลาดในการอัปเดตโปรไฟล์";
        }
    } else if ($role === 'employer') {
        $company_name = $_POST['company_name'] ?? '';
        $details = $_POST['details'] ?? '';

        $stmt = $conn->prepare("UPDATE employer_profiles SET company_name=?, details=? WHERE user_id=?");
        if ($stmt->execute([$company_name, $details, $user_id])) {
            $success = "อัปเดตโปรไฟล์สำเร็จ";
        } else {
            $error = "เกิดข้อผิดพลาดในการอัปเดตโปรไฟล์";
        }
    }
    
    // Handle Profile Image Upload (simplified, needs directory creation)
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $upload_dir = 'uploads/avatars/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $filename = time() . '_' . basename($_FILES['profile_image']['name']);
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
            $table = ($role === 'musician') ? 'musician_profiles' : 'employer_profiles';
            $stmt = $conn->prepare("UPDATE $table SET profile_image=? WHERE user_id=?");
            $stmt->execute([$filename, $user_id]);
        }
    }
}

// Handle Portfolio Addition (Musician Only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_portfolio']) && $role === 'musician') {
    $type = $_POST['portfolio_type'];
    $link = $_POST['portfolio_link'];
    $description = $_POST['portfolio_description'] ?? '';
    
    if (!empty($link)) {
        $stmt = $conn->prepare("INSERT INTO portfolios (musician_id, type, link, description) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $type, $link, $description])) {
            $success = "เพิ่มผลงานสำเร็จ";
        } else {
            $error = "เกิดข้อผิดพลาดในการเพิ่มผลงาน";
        }
    }
}

// Fetch Profile Data
$profile = [];
if ($role === 'musician') {
    $stmt = $conn->prepare("SELECT * FROM musician_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    
    $stmt = $conn->prepare("SELECT * FROM portfolios WHERE musician_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $portfolios = $stmt->fetchAll();
} else if ($role === 'employer') {
    $stmt = $conn->prepare("SELECT * FROM employer_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
}

$stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
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

                        <?php if ($role === 'musician'): ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ประเภท</label>
                                    <select class="form-select" name="band_type">
                                        <option value="solo" <?php echo ($profile['band_type'] == 'solo') ? 'selected' : ''; ?>>ศิลปินเดี่ยว</option>
                                        <option value="band" <?php echo ($profile['band_type'] == 'band') ? 'selected' : ''; ?>>วงดนตรี</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">แนวเพลงที่ถนัด (คั่นด้วยลูกน้ำ)</label>
                                    <input type="text" class="form-control" name="genres" value="<?php echo htmlspecialchars($profile['genres'] ?? ''); ?>" placeholder="เช่น Pop, Rock, Jazz">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">เรทค่าจ้าง (เริ่มต้น)</label>
                                    <input type="text" class="form-control" name="rate" value="<?php echo htmlspecialchars($profile['rate'] ?? ''); ?>" placeholder="เช่น 1,500 บาท/ชม.">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">เขตพื้นที่รับงาน</label>
                                    <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($profile['location'] ?? ''); ?>" placeholder="เช่น กรุงเทพฯ, ปริมณฑล">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">วันเวลาที่ว่าง (โดยสังเขป)</label>
                                <textarea class="form-control" name="availability_info" rows="2" placeholder="เช่น ว่างทุกวันศุกร์-เสาร์ ช่วงเย็นเป็นต้นไป"><?php echo htmlspecialchars($profile['availability_info'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">แนะนำตัว / ประวัติ</label>
                                <textarea class="form-control" name="bio" rows="4"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                            </div>

                        <?php elseif ($role === 'employer'): ?>
                            <div class="mb-3">
                                <label class="form-label">ชื่อบริษัท / ชื่อร้าน / ชื่อผู้จัด</label>
                                <input type="text" class="form-control" name="company_name" value="<?php echo htmlspecialchars($profile['company_name'] ?? ''); ?>">
                            </div>
                            <div class="mb-4">
                                <label class="form-label">รายละเอียดเพิ่มเติม</label>
                                <textarea class="form-control" name="details" rows="4"><?php echo htmlspecialchars($profile['details'] ?? ''); ?></textarea>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-save me-2"></i>บันทึกข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Portfolio Section for Musicians -->
            <?php if ($role === 'musician'): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold"><i class="fas fa-photo-video text-primary me-2"></i>ผลงานของฉัน</h5>
                    <button class="btn btn-sm btn-outline-primary rounded-pill" data-bs-toggle="collapse" data-bs-target="#addPortfolioForm">
                        <i class="fas fa-plus"></i> เพิ่มผลงาน
                    </button>
                </div>
                <div class="card-body">
                    <!-- Add Portfolio Form -->
                    <div class="collapse mb-4" id="addPortfolioForm">
                        <div class="card card-body bg-light">
                            <form method="POST" action="profile.php">
                                <input type="hidden" name="add_portfolio" value="1">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">ประเภท</label>
                                        <select class="form-select" name="portfolio_type">
                                            <option value="video">ลิงก์วิดีโอ (YouTube)</option>
                                            <option value="audio">ลิงก์เสียง (SoundCloud)</option>
                                            <option value="image">ลิงก์รูปภาพ</option>
                                        </select>
                                    </div>
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">ลิงก์ URL</label>
                                        <input type="url" class="form-control" name="portfolio_link" required placeholder="https://...">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">คำอธิบาย</label>
                                    <input type="text" class="form-control" name="portfolio_description" placeholder="เช่น ร้องสดที่ร้าน...">
                                </div>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary btn-sm">บันทึกผลงาน</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Portfolio List -->
                    <?php if (count($portfolios) > 0): ?>
                        <div class="row">
                            <?php foreach ($portfolios as $item): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100 border">
                                        <div class="card-body p-3">
                                            <?php if ($item['type'] === 'video'): ?>
                                                <div class="text-center mb-2">
                                                    <i class="fab fa-youtube text-danger fa-2x"></i>
                                                </div>
                                            <?php elseif ($item['type'] === 'audio'): ?>
                                                <div class="text-center mb-2">
                                                    <i class="fab fa-soundcloud text-warning fa-2x"></i>
                                                </div>
                                            <?php endif; ?>
                                            <h6 class="card-title text-truncate"><?php echo htmlspecialchars($item['description']); ?></h6>
                                            <a href="<?php echo htmlspecialchars($item['link']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary w-100 mt-2">เปิดดู <i class="fas fa-external-link-alt ms-1"></i></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">ยังไม่มีผลงาน</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
