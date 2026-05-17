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
$musician_id = $user_id;

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



<?php
// PHP helper to extract YouTube ID
function get_youtube_video_id($url) {
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|[^/]+[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
    return isset($match[1]) ? $match[1] : null;
}
?>

<div class="container py-5 mt-5">
    <!-- Header Title -->
    <div class="text-center text-lg-start mb-5">
        <h2 class="text-white fw-bold mb-1" style="letter-spacing: 1px; text-shadow: 0 0 15px rgba(255, 255, 255, 0.1);">
            <i class="fas fa-photo-video text-pink me-2 animate__pulse"></i>จัดการคลังผลงาน (Portfolio)
        </h2>
        <p class="text-secondary small mb-0">สร้างและปรับปรุงคลังรูปภาพ คลิปวิดีโอ และลิงก์แสดงดนตรีสดเพื่อประกอบการตัดสินใจของนายจ้าง</p>
    </div>

    <div class="row">
        <!-- Left: Upload/Add Form Box -->
        <div class="col-lg-4 mb-4 mb-lg-0">
            <div class="card glass-card-premium p-4 border border-secondary border-opacity-15 rounded-4 bg-dark bg-opacity-20">
                <h4 class="text-white fw-bold mb-4" style="font-size: 1.25rem;">
                    <i class="fas fa-plus-circle text-cyan me-2"></i>เพิ่มผลงานใหม่
                </h4>
                
                <?php if ($success): ?>
                    <div class="alert alert-success border-0 bg-success bg-opacity-15 text-success rounded-4 p-3 mb-4 animate__animated animate__fadeIn">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 bg-danger bg-opacity-15 text-danger rounded-4 p-3 mb-4 animate__animated animate__fadeIn">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <ul class="nav nav-pills nav-justified portfolio-tabs mb-4 gap-2" id="portfolioTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-link" type="button">
                            <i class="fas fa-link me-1"></i>เพิ่มลิงก์
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-file" type="button">
                            <i class="fas fa-cloud-upload-alt me-1"></i>อัปโหลดไฟล์
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="portfolioTabsContent">
                    <!-- Add Link Form -->
                    <div class="tab-pane fade show active" id="tab-link" role="tabpanel">
                        <form action="portfolio_manager.php" method="POST">
                            <input type="hidden" name="action" value="add_link">
                            <div class="mb-3">
                                <label class="text-secondary small fw-bold mb-1 d-block">ลิงก์ผลงาน (YouTube, Facebook, Soundcloud)</label>
                                <div class="form-input-icon-wrapper">
                                    <i class="fas fa-globe text-cyan"></i>
                                    <input type="url" class="form-control form-control-premium" name="link" placeholder="https://..." required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="text-secondary small fw-bold mb-1 d-block">คำอธิบายผลงาน</label>
                                <div class="form-input-icon-wrapper">
                                    <i class="fas fa-comment-dots text-pink"></i>
                                    <input type="text" class="form-control form-control-premium" name="description" placeholder="เช่น บันทึกการแสดงสดคัพเวอร์เพลง..." required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-outline-cyan w-100 py-2.5 rounded-pill fw-bold">
                                <i class="fas fa-save me-2"></i>บันทึกลิงก์
                            </button>
                        </form>
                    </div>

                    <!-- Upload File Form -->
                    <div class="tab-pane fade" id="tab-file" role="tabpanel">
                        <form action="portfolio_manager.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_file">
                            <div class="mb-3">
                                <label class="text-secondary small fw-bold mb-1 d-block">ประเภทไฟล์ผลงาน</label>
                                <div class="form-input-icon-wrapper">
                                    <i class="fas fa-photo-video text-cyan"></i>
                                    <select class="form-select form-control-premium text-white bg-transparent border-0" name="file_type" style="padding-left: 2.75rem;">
                                        <option value="image" class="bg-dark text-white">รูปภาพภาพนิ่ง (Image)</option>
                                        <option value="video" class="bg-dark text-white">ไฟล์วิดีโอ (Video MP4)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="text-secondary small fw-bold mb-1 d-block">เลือกไฟล์ผลงาน</label>
                                <div class="custom-file-upload-wrapper" onclick="document.getElementById('portfolio_file').click()">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <h6 class="text-light fw-bold mb-1">คลิกเลือกไฟล์ผลงาน</h6>
                                    <p class="text-secondary small mb-0">รูปภาพ (JPG, PNG, WEBP) หรือวิดีโอ (MP4)</p>
                                    <input type="file" id="portfolio_file" name="portfolio_file" class="d-none" onchange="updateFileNameLabel(this)" required>
                                    <div id="file_name_label" class="mt-3 text-cyan small fw-bold" style="display: none; text-shadow: 0 0 10px rgba(0, 240, 255, 0.3);"></div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="text-secondary small fw-bold mb-1 d-block">คำอธิบายภาพ/วิดีโอ</label>
                                <div class="form-input-icon-wrapper">
                                    <i class="fas fa-comment-dots text-pink"></i>
                                    <input type="text" class="form-control form-control-premium" name="description" placeholder="เช่น รูปภาพบรรยากาศคอนเสิร์ต..." required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-outline-pink w-100 py-2.5 rounded-pill fw-bold">
                                <i class="fas fa-cloud-upload-alt me-2"></i>อัปโหลดผลงาน
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Current Portfolios List -->
        <div class="col-lg-8">
            <h4 class="text-white fw-bold mb-4" style="font-size: 1.25rem;">
                <i class="fas fa-photo-video text-pink me-2"></i>คลังผลงานที่กำลังแสดงบนโปรไฟล์ของคุณ
            </h4>
            
            <div class="row">
                <?php if (count($portfolios) > 0): ?>
                    <?php foreach ($portfolios as $p): ?>
                        <div class="col-md-6 mb-4">
                            <div class="portfolio-card-premium h-100">
                                <!-- Media Top Section -->
                                <?php if ($p['type'] == 'image'): ?>
                                    <span class="portfolio-badge portfolio-badge-image">
                                        <i class="fas fa-image me-1"></i>รูปภาพ
                                    </span>
                                    <div class="portfolio-media-wrapper">
                                        <img src="uploads/portfolios/<?php echo htmlspecialchars($p['link']); ?>" alt="Portfolio Image">
                                    </div>
                                <?php elseif ($p['type'] == 'video'): ?>
                                    <span class="portfolio-badge portfolio-badge-video">
                                        <i class="fas fa-video me-1"></i>วิดีโอ
                                    </span>
                                    <div class="portfolio-media-wrapper">
                                        <video src="uploads/portfolios/<?php echo htmlspecialchars($p['link']); ?>" controls></video>
                                    </div>
                                <?php elseif ($p['type'] == 'link'): ?>
                                    <span class="portfolio-badge portfolio-badge-link">
                                        <i class="fas fa-link me-1"></i>ลิงก์ผลงาน
                                    </span>
                                    
                                    <?php 
                                    $yt_id = get_youtube_video_id($p['link']);
                                    if ($yt_id): 
                                        $thumb_url = "https://img.youtube.com/vi/{$yt_id}/hqdefault.jpg";
                                    ?>
                                        <a href="<?php echo htmlspecialchars($p['link']); ?>" target="_blank" class="portfolio-media-wrapper d-block position-relative">
                                            <div class="youtube-play-btn"><i class="fas fa-play"></i></div>
                                            <img src="<?php echo $thumb_url; ?>" alt="YouTube Cover">
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars($p['link']); ?>" target="_blank" class="portfolio-media-wrapper d-block">
                                            <div class="link-placeholder-box">
                                                <i class="fas fa-external-link-alt fa-3x mb-3 text-cyan opacity-40"></i>
                                                <h6 class="text-light fw-bold mb-0">คลิกชมผลงานภายนอก</h6>
                                                <p class="small text-secondary mb-0 mt-1"><?php echo parse_url($p['link'], PHP_URL_HOST); ?></p>
                                            </div>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <!-- Card Info Body -->
                                <div class="card-body p-4 flex-grow-1 d-flex flex-column justify-content-between">
                                    <div>
                                        <p class="card-text text-light small mb-3" style="line-height: 1.5; font-size: 0.9rem;">
                                            <?php echo htmlspecialchars($p['description']); ?>
                                        </p>
                                    </div>
                                    
                                    <?php if ($p['type'] == 'link'): ?>
                                        <div class="mb-3">
                                            <a href="<?php echo htmlspecialchars($p['link']); ?>" target="_blank" class="text-cyan small text-truncate d-block text-decoration-none">
                                                <i class="fas fa-link me-1"></i><?php echo htmlspecialchars($p['link']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="border-top border-secondary border-opacity-10 pt-3 text-end">
                                        <form action="portfolio_manager.php" method="POST" onsubmit="return confirm('คุณต้องการยืนยันการลบผลงานชิ้นนี้ออกจากระบบหรือไม่?');" class="d-inline">
                                            <input type="hidden" name="action" value="delete_portfolio">
                                            <input type="hidden" name="portfolio_id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1">
                                                <i class="fas fa-trash me-1"></i>ลบผลงาน
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <div class="card glass-card-premium py-5 px-4 text-center border border-secondary border-opacity-15">
                            <i class="fas fa-photo-video fa-4x mb-3 text-pink opacity-30 animate__pulse"></i>
                            <h5 class="text-light fw-bold">ยังไม่พบผลงานดนตรีของคุณในระบบ</h5>
                            <p class="text-secondary small mb-0">เริ่มต้นเพิ่มคลังผลงานชิ้นแรกของคุณผ่านการกรอกฟอร์มซ้ายมือได้เลยครับ!</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function updateFileNameLabel(input) {
    const label = document.getElementById('file_name_label');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileSizeMB = file.size / (1024 * 1024);
        
        // Check if it's a video file (exempt from size limit check)
        const isVideo = file.type.startsWith('video/') || /\.(mp4|webm|ogg|avi|mov|mkv)$/i.test(file.name);
        
        // 40MB limit check (Only for non-video files!)
        if (!isVideo && fileSizeMB > 40) {
            alert(`⚠️ ไฟล์รูปภาพมีขนาดใหญ่เกินกำหนด! (ขนาดไฟล์ของคุณ: ${fileSizeMB.toFixed(2)} MB, จำกัดสูงสุดไม่เกิน 40 MB)\n\nกรุณาอัปโหลดไฟล์ที่มีขนาดเล็กลงครับ`);
            input.value = ''; // clear chosen file
            label.style.display = 'none';
            return;
        }
        
        label.innerText = `📁 ไฟล์ที่เลือก: ${file.name} (${fileSizeMB.toFixed(2)} MB)`;
        label.style.display = 'block';
    } else {
        label.style.display = 'none';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
