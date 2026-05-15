<?php
// community_profile_setup.php
require_once 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Check if profile exists
$stmt = $conn->prepare("SELECT * FROM community_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $display_name = trim($_POST['display_name']);
    $username = trim($_POST['community_username']);
    $bio = trim($_POST['bio']);
    $avatar = $profile['avatar'] ?? 'default_avatar.png';

    if (empty($display_name) || empty($username)) {
        $error = "กรุณากรอกชื่อที่ต้องการแสดงและชื่อผู้ใช้งาน";
    } else {
        // Handle Avatar Upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $avatar = uniqid() . '.' . $ext;
                if (!is_dir('uploads/avatars')) mkdir('uploads/avatars', 0777, true);
                move_uploaded_file($_FILES['avatar']['tmp_name'], 'uploads/avatars/' . $avatar);
            }
        }

        try {
            if ($profile) {
                $stmt = $conn->prepare("UPDATE community_profiles SET display_name = ?, community_username = ?, avatar = ?, bio = ? WHERE user_id = ?");
                $stmt->execute([$display_name, $username, $avatar, $bio, $user_id]);
            } else {
                $stmt = $conn->prepare("INSERT INTO community_profiles (user_id, display_name, community_username, avatar, bio) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $display_name, $username, $avatar, $bio]);
            }
            header("Location: community.php?profile=updated");
            exit();
        } catch (Exception $e) {
            $error = "ชื่อผู้ใช้งานนี้ถูกใช้ไปแล้ว หรือเกิดข้อผิดพลาดอื่น";
        }
    }
}

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="glass-card p-5 shadow-glow">
                <h2 class="fw-bold text-white mb-4">ตั้งค่าโปรไฟล์ Community 🎭</h2>
                <p class="text-secondary mb-4">แยกตัวตนในโลกสังคมออนไลน์ของคุณจากโปรไฟล์ทำงานหลัก</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form action="community_profile_setup.php" method="POST" enctype="multipart/form-data">
                    <div class="text-center mb-4">
                        <img src="uploads/avatars/<?php echo htmlspecialchars($profile['avatar'] ?? 'default_avatar.png'); ?>" class="profile-img-large mb-3 shadow" id="avatarPreview" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-color);">
                        <input type="file" name="avatar" id="avatarInput" class="d-none" onchange="previewAvatar(this)">
                        <button type="button" class="btn btn-sm btn-outline-primary d-block mx-auto" onclick="document.getElementById('avatarInput').click()">เปลี่ยนรูปโปรไฟล์</button>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-white">ชื่อที่ต้องการให้คนอื่นเห็น (Display Name)</label>
                        <input type="text" name="display_name" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($profile['display_name'] ?? ''); ?>" placeholder="เช่น RockStar99" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-white">ชื่อผู้ใช้งานสำหรับ Community (Username)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-secondary border-secondary text-white">@</span>
                            <input type="text" name="community_username" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($profile['community_username'] ?? ''); ?>" placeholder="username" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-white">แนะนำตัวสั้นๆ</label>
                        <textarea name="bio" class="form-control bg-dark border-secondary text-white" rows="3"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-bold">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
