<?php
// community_profile_setup.php
require_once 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch current data
$stmt = $conn->prepare("SELECT * FROM community_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $display_name = trim($_POST['display_name']);
    $community_username = trim($_POST['community_username']);
    $bio = trim($_POST['bio']);
    
    $avatar = $profile['avatar'] ?? 'default_avatar.png';
    $banner = $profile['banner'] ?? null;

    // Handle Avatar Upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
        if (!is_dir('uploads/avatars')) mkdir('uploads/avatars', 0777, true);
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], 'uploads/avatars/' . $filename)) {
            $avatar = $filename;
        }
    }

    // Handle Banner Upload
    if (isset($_FILES['banner']) && $_FILES['banner']['error'] == 0) {
        $ext = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
        $filename = 'banner_' . $user_id . '_' . time() . '.' . $ext;
        if (!is_dir('uploads/banners')) mkdir('uploads/banners', 0777, true);
        if (move_uploaded_file($_FILES['banner']['tmp_name'], 'uploads/banners/' . $filename)) {
            $banner = $filename;
        }
    }

    if (!empty($profile)) {
        $stmt = $conn->prepare("UPDATE community_profiles SET display_name = ?, community_username = ?, bio = ?, avatar = ?, banner = ? WHERE user_id = ?");
        $stmt->execute([$display_name, $community_username, $bio, $avatar, $banner, $user_id]);
    } else {
        $stmt = $conn->prepare("INSERT INTO community_profiles (user_id, display_name, community_username, bio, avatar, banner) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $display_name, $community_username, $bio, $avatar, $banner]);
    }
    
    $message = '<div class="alert alert-success">บันทึกโปรไฟล์สำเร็จ!</div>';
    // Refresh data
    $stmt = $conn->prepare("SELECT * FROM community_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

$avatar_src = (!empty($profile['avatar']) && $profile['avatar'] !== 'default_avatar.png') ? 'uploads/avatars/' . $profile['avatar'] : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['username']);
$banner_src = !empty($profile['banner']) ? 'uploads/banners/' . $profile['banner'] : 'https://images.unsplash.com/photo-1514525253361-bee8718a300c?w=1200&h=400&fit=crop';
?>
<?php include 'includes/header.php'; ?>
    <style>
        .setup-container { max-width: 900px; margin: 40px auto; }
        .setup-card { background: rgba(25, 25, 25, 0.95); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.5); }
        
        /* Banner Styling */
        .banner-editor {
            height: 280px;
            background: url('<?php echo $banner_src; ?>') center/cover no-repeat;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .banner-editor::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.2);
            transition: 0.3s;
        }
        .banner-editor:hover::after {
            background: rgba(0, 0, 0, 0.4);
        }
        .banner-upload-icon {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10;
            background: rgba(0, 0, 0, 0.6);
            width: 60px; height: 60px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff; border: 2px solid rgba(255, 255, 255, 0.5);
            opacity: 0.8; transition: 0.3s;
        }
        .banner-editor:hover .banner-upload-icon {
            transform: translate(-50%, -50%) scale(1.1);
            opacity: 1;
            background: var(--primary-color);
            border-color: #fff;
        }

        /* Avatar Styling */
        .avatar-editor-wrapper {
            position: absolute;
            bottom: -60px;
            left: 40px;
            z-index: 20;
        }
        .avatar-editor {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 6px solid #1a1a1a;
            background: #222;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
        }
        .avatar-editor img {
            width: 100%; height: 100%; object-fit: cover;
        }
        .avatar-upload-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.4);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: 0.3s;
            color: #fff;
        }
        .avatar-editor:hover .avatar-upload-overlay {
            opacity: 1;
        }
        
        .setup-body { padding: 80px 40px 40px; }
        .form-label { font-weight: 600; color: #aaa; margin-bottom: 8px; }
        .form-control { background: rgba(255,255,255,0.05) !important; border: 1px solid rgba(255,255,255,0.1) !important; color: #fff !important; padding: 12px 15px; border-radius: 12px; }
        .form-control:focus { background: rgba(255,255,255,0.08) !important; border-color: var(--primary-color) !important; box-shadow: none !important; }
    </style>

    <div class="container setup-container">
        <div class="setup-card animate__animated animate__fadeIn">
            <form action="" method="POST" enctype="multipart/form-data">
                <!-- Banner & Avatar Editor Area -->
                <div class="position-relative">
                    <div class="banner-editor" id="bannerPreviewBox" onclick="document.getElementById('bannerInput').click()">
                        <div class="banner-upload-icon shadow-lg">
                            <i class="fas fa-camera fa-lg"></i>
                        </div>
                        <div class="position-absolute bottom-0 end-0 m-3 z-3">
                            <span class="badge bg-dark bg-opacity-75 rounded-pill px-3 py-2"><i class="fas fa-info-circle me-1"></i> คลิกเพื่อเปลี่ยนรูปปก</span>
                        </div>
                    </div>

                    <div class="avatar-editor-wrapper">
                        <div class="avatar-editor" onclick="document.getElementById('avatarInput').click()">
                            <img src="<?php echo $avatar_src; ?>" id="avatarPreviewImg">
                            <div class="avatar-upload-overlay">
                                <i class="fas fa-camera fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="setup-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="fw-bold mb-1">ปรับแต่งโปรไฟล์คอมมูนิตี้</h4>
                            <p class="text-secondary small mb-0">สร้างตัวตนของคุณให้โดดเด่นในโลกดนตรี</p>
                        </div>
                        <a href="community.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                            <i class="fas fa-times me-1"></i> ยกเลิก
                        </a>
                    </div>

                    <?php echo $message; ?>

                    <input type="file" name="banner" id="bannerInput" class="d-none" onchange="previewFile(this, 'bannerPreviewBox')">
                    <input type="file" name="avatar" id="avatarInput" class="d-none" onchange="previewFile(this, 'avatarPreviewImg')">

                    <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-secondary small">ชื่อที่แสดงผล (Display Name)</label>
                        <input type="text" name="display_name" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($profile['display_name'] ?? $_SESSION['username']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-secondary small">ชื่อผู้ใช้คอมมูนิตี้ (@username)</label>
                        <input type="text" name="community_username" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($profile['community_username'] ?? $_SESSION['username']); ?>" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-secondary small">ประวัติย่อ (Bio)</label>
                    <textarea name="bio" class="form-control bg-dark border-secondary text-white" rows="4" placeholder="แนะนำตัวสั้นๆ ให้เพื่อนร่วมวงรู้จักคุณ..."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-glow">บันทึกการเปลี่ยนแปลง</button>
                    <a href="community_profile.php?username=<?php echo $profile['community_username'] ?? $_SESSION['username']; ?>" class="btn btn-outline-secondary rounded-pill px-4">ดูโปรไฟล์</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    function previewFile(input, targetId) {
        const file = input.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const target = document.getElementById(targetId);
                if (targetId === 'bannerPreviewBox') {
                    target.style.backgroundImage = `url('${e.target.result}')`;
                } else {
                    target.src = e.target.result;
                }
            }
            reader.readAsDataURL(file);
        }
    }
    </script>
    <?php include 'includes/footer.php'; ?>
