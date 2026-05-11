<?php
require_once 'includes/db.php';
session_start();

$success = '';
$error = '';

// Handle New Post
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_post']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $content = trim($_POST['content']);
    
    if (!empty($content)) {
        $stmt = $conn->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)");
        if ($stmt->execute([$user_id, $content])) {
            $success = "โพสต์ข้อความสำเร็จ";
        } else {
            $error = "เกิดข้อผิดพลาดในการโพสต์";
        }
    } else {
        $error = "กรุณากรอกข้อความที่ต้องการโพสต์";
    }
}

// Fetch Posts
$stmt = $conn->prepare("
    SELECT p.*, u.username, u.role, m.profile_image as m_img, e.profile_image as e_img 
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    LEFT JOIN musician_profiles m ON u.id = m.user_id 
    LEFT JOIN employer_profiles e ON u.id = e.user_id 
    ORDER BY p.created_at DESC
");
$stmt->execute();
$posts = $stmt->fetchAll();
?>
<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold"><i class="fas fa-users text-primary me-2"></i>กระดานข่าวสารและชุมชน</h2>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <!-- Post Box -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="POST" action="feed.php">
                            <input type="hidden" name="submit_post" value="1">
                            <div class="mb-3">
                                <textarea name="content" class="form-control border-0 bg-light" rows="3" placeholder="แชร์ข่าวสาร, โปรโมทงาน, หานักดนตรี..." required></textarea>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small"><i class="fas fa-info-circle me-1"></i> โพสต์จะถูกแสดงในหน้าแรกและกระดานข่าว</span>
                                <button type="submit" class="btn btn-primary rounded-pill px-4">โพสต์เลย</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info shadow-sm text-center">
                    กรุณา <a href="login.php" class="alert-link">เข้าสู่ระบบ</a> เพื่อทำการโพสต์ข่าวสาร
                </div>
            <?php endif; ?>

            <!-- Feed -->
            <div class="posts-container">
                <?php if (count($posts) > 0): ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="card shadow-sm mb-4 border-0">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center">
                                        <?php 
                                        $img = $post['role'] === 'musician' ? $post['m_img'] : $post['e_img'];
                                        $img_src = !empty($img) && $img !== 'default_avatar.png' ? 'uploads/avatars/' . $img : 'https://ui-avatars.com/api/?name='.urlencode($post['username']).'&background=0D8ABC&color=fff';
                                        ?>
                                        <img src="<?php echo htmlspecialchars($img_src); ?>" class="profile-img-small me-3" alt="Profile">
                                        <div>
                                            <h6 class="mb-0 fw-bold">
                                                <?php echo htmlspecialchars($post['username']); ?>
                                                <?php if ($post['role'] === 'musician'): ?>
                                                    <span class="badge bg-primary ms-1" style="font-size: 0.6rem;">นักดนตรี</span>
                                                <?php elseif ($post['role'] === 'employer'): ?>
                                                    <span class="badge bg-success ms-1" style="font-size: 0.6rem;">ผู้ว่าจ้าง</span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted"><?php echo date('d M Y, H:i', strtotime($post['created_at'])); ?></small>
                                        </div>
                                    </div>
                                    <?php if ($post['role'] === 'musician'): ?>
                                        <a href="musician_profile.php?id=<?php echo $post['user_id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill">ดูโปรไฟล์</a>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="card-text fs-5" style="white-space: pre-wrap;"><?php echo htmlspecialchars($post['content']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="far fa-comment-dots fa-3x mb-3"></i>
                        <h5>ยังไม่มีโพสต์ในกระดานข่าว</h5>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
