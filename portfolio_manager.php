<?php
require_once 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'musician') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Check if setup success message
if (isset($_GET['setup']) && $_GET['setup'] == 'success') {
    $success = "อัปเกรดบัญชีเป็นนักดนตรีสำเร็จ! กรุณาเพิ่มผลงานของคุณเพื่อให้ผู้ว่าจ้างเห็น";
}

// Check musician_id
$stmt = $conn->prepare("SELECT id FROM musician_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$musician = $stmt->fetch();

if (!$musician) {
    header("Location: become_musician.php");
    exit();
}
$musician_id = $musician['id'];

// Handle upload
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'add_link') {
        $link = trim($_POST['link']);
        $description = trim($_POST['description']);
        
        if (!empty($link)) {
            $stmt = $conn->prepare("INSERT INTO portfolios (musician_id, type, link, description) VALUES (?, 'link', ?, ?)");
            if ($stmt->execute([$musician_id, $link, $description])) {
                $success = "เพิ่มลิงก์ผลงานสำเร็จ";
            } else {
                $error = "เกิดข้อผิดพลาดในการเพิ่มลิงก์";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'upload_file') {
        $description = trim($_POST['description']);
        $type = $_POST['file_type']; // image or video
        
        if (isset($_FILES['portfolio_file']) && $_FILES['portfolio_file']['error'] == 0) {
            $allowed_img = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowed_vid = ['mp4', 'webm', 'ogg'];
            
            $filename = $_FILES['portfolio_file']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            $is_valid = false;
            if ($type == 'image' && in_array($ext, $allowed_img)) $is_valid = true;
            if ($type == 'video' && in_array($ext, $allowed_vid)) $is_valid = true;
            
            if ($is_valid) {
                $new_filename = uniqid() . '.' . $ext;
                $upload_dir = 'uploads/portfolios/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                if (move_uploaded_file($_FILES['portfolio_file']['tmp_name'], $upload_dir . $new_filename)) {
                    $stmt = $conn->prepare("INSERT INTO portfolios (musician_id, type, link, description) VALUES (?, ?, ?, ?)");
                    if ($stmt->execute([$musician_id, $type, $new_filename, $description])) {
                        $success = "อัปโหลดไฟล์ผลงานสำเร็จ";
                    }
                } else {
                    $error = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
                }
            } else {
                $error = "ประเภทไฟล์ไม่รองรับ หรือเลือกประเภทไฟล์ผิด";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete_portfolio') {
        $port_id = $_POST['portfolio_id'];
        // Fetch to delete file if exists
        $stmt = $conn->prepare("SELECT type, link FROM portfolios WHERE id = ? AND musician_id = ?");
        $stmt->execute([$port_id, $musician_id]);
        $port = $stmt->fetch();
        if ($port) {
            if ($port['type'] == 'image' || $port['type'] == 'video') {
                $file_path = 'uploads/portfolios/' . $port['link'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            $stmt = $conn->prepare("DELETE FROM portfolios WHERE id = ?");
            $stmt->execute([$port_id]);
            $success = "ลบผลงานสำเร็จ";
        }
    }
}

// Fetch existing portfolios
$stmt = $conn->prepare("SELECT * FROM portfolios WHERE musician_id = ? ORDER BY created_at DESC");
$stmt->execute([$musician_id]);
$portfolios = $stmt->fetchAll();
?>

<?php include 'includes/header.php'; ?>

<div class="container py-5 mt-5">
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card bg-dark border-secondary shadow-lg">
                <div class="card-body p-4">
                    <h4 class="text-white fw-bold mb-4"><i class="fas fa-plus-circle text-primary me-2"></i>เพิ่มผลงานใหม่</h4>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?></div>
                    <?php endif; ?>

                    <ul class="nav nav-pills nav-justified mb-4" id="portfolioTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-link" type="button"><i class="fas fa-link mb-1 d-block"></i>เพิ่มลิงก์</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-file" type="button"><i class="fas fa-upload mb-1 d-block"></i>อัปโหลดไฟล์</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="portfolioTabsContent">
                        <!-- Add Link Form -->
                        <div class="tab-pane fade show active" id="tab-link" role="tabpanel">
                            <form action="portfolio_manager.php" method="POST">
                                <input type="hidden" name="action" value="add_link">
                                <div class="mb-3">
                                    <label class="form-label text-light">ลิงก์ผลงาน (YouTube, Facebook, ฯลฯ)</label>
                                    <input type="url" class="form-control bg-dark border-secondary text-white" name="link" placeholder="https://..." required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-light">คำอธิบาย</label>
                                    <input type="text" class="form-control bg-dark border-secondary text-white" name="description" placeholder="เช่น ร้องโคฟเวอร์เพลง...">
                                </div>
                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>บันทึกลิงก์</button>
                            </form>
                        </div>

                        <!-- Upload File Form -->
                        <div class="tab-pane fade" id="tab-file" role="tabpanel">
                            <form action="portfolio_manager.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload_file">
                                <div class="mb-3">
                                    <label class="form-label text-light">ประเภทไฟล์</label>
                                    <select class="form-select bg-dark border-secondary text-white" name="file_type">
                                        <option value="image">รูปภาพ (Image)</option>
                                        <option value="video">วิดีโอ (Video MP4)</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-light">เลือกไฟล์</label>
                                    <input type="file" class="form-control bg-dark border-secondary text-white" name="portfolio_file" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-light">คำอธิบาย</label>
                                    <input type="text" class="form-control bg-dark border-secondary text-white" name="description" placeholder="เช่น รูปภาพบรรยากาศงาน...">
                                </div>
                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-upload me-2"></i>อัปโหลดไฟล์</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <h4 class="text-white fw-bold mb-4"><i class="fas fa-photo-video text-primary me-2"></i>ผลงานของคุณ</h4>
            <div class="row">
                <?php if (count($portfolios) > 0): ?>
                    <?php foreach ($portfolios as $p): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card bg-dark border-secondary h-100 shadow-sm">
                                <?php if ($p['type'] == 'image'): ?>
                                    <img src="uploads/portfolios/<?php echo htmlspecialchars($p['link']); ?>" class="card-img-top" style="height: 200px; object-fit: cover;" alt="Portfolio Image">
                                <?php elseif ($p['type'] == 'video'): ?>
                                    <video src="uploads/portfolios/<?php echo htmlspecialchars($p['link']); ?>" class="card-img-top" style="height: 200px; object-fit: cover; background:#000;" controls></video>
                                <?php elseif ($p['type'] == 'link'): ?>
                                    <div class="card-img-top d-flex align-items-center justify-content-center bg-secondary" style="height: 200px;">
                                        <i class="fas fa-link fa-4x text-light opacity-50"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <?php if ($p['type'] == 'link'): ?>
                                        <p class="card-text text-truncate mb-2"><a href="<?php echo htmlspecialchars($p['link']); ?>" target="_blank" class="text-info"><?php echo htmlspecialchars($p['link']); ?></a></p>
                                    <?php endif; ?>
                                    <p class="card-text text-light"><?php echo htmlspecialchars($p['description']); ?></p>
                                </div>
                                <div class="card-footer bg-transparent border-secondary text-end">
                                    <form action="portfolio_manager.php" method="POST" onsubmit="return confirm('คุณต้องการลบผลงานชิ้นนี้หรือไม่?');">
                                        <input type="hidden" name="action" value="delete_portfolio">
                                        <input type="hidden" name="portfolio_id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>ลบ</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <p class="text-muted fs-5"><i class="fas fa-inbox fa-2x d-block mb-3 opacity-50"></i>ยังไม่มีผลงาน เพิ่มผลงานแรกของคุณเลย!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
