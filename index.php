<?php
require_once 'includes/db.php';
session_start();

// Fetch Top Musicians
$stmt = $conn->prepare("
    SELECT u.id as user_id, u.username, m.profile_image, m.band_type, m.genres, m.rate, m.rating_score
    FROM users u
    JOIN musician_profiles m ON u.id = m.user_id
    WHERE u.role = 'musician' AND m.is_verified = 1
    ORDER BY m.rating_score DESC
    LIMIT 6
");
$stmt->execute();
$top_musicians = $stmt->fetchAll();

// Fetch Recent Promoted Posts
$stmt = $conn->prepare("
    SELECT p.content, p.created_at, u.username, m.profile_image
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN musician_profiles m ON u.id = m.user_id
    ORDER BY p.created_at DESC
    LIMIT 3
");
$stmt->execute();
$recent_posts = $stmt->fetchAll();
?>
<?php include 'includes/header.php'; ?>

<!-- Hero Section -->
<div class="bg-primary text-white text-center py-5" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);">
    <div class="container py-5">
        <h1 class="display-4 fw-bold mb-3">ค้นหานักดนตรีอิสระที่ใช่ สำหรับงานของคุณ</h1>
        <p class="lead mb-4">จ้างนักดนตรีเดี่ยว วงดนตรี ทุกแนวเพลง ได้ง่ายๆ และปลอดภัย</p>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <form action="search.php" method="GET" class="d-flex bg-white p-2 rounded-pill shadow">
                    <input type="text" name="q" class="form-control border-0 shadow-none ps-4" placeholder="ค้นหาตามชื่อวง, แนวเพลง หรือสถานที่...">
                    <button type="submit" class="btn btn-warning rounded-pill px-4 text-dark fw-bold">ค้นหาเลย</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Top Musicians Section -->
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="fas fa-star text-warning me-2"></i>นักดนตรียอดนิยม</h3>
        <a href="search.php" class="text-decoration-none">ดูทั้งหมด <i class="fas fa-arrow-right"></i></a>
    </div>
    
    <div class="row">
        <?php if (count($top_musicians) > 0): ?>
            <?php foreach ($top_musicians as $musician): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <?php 
                            $img_src = !empty($musician['profile_image']) && $musician['profile_image'] !== 'default_avatar.png' ? 'uploads/avatars/' . $musician['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($musician['username']).'&background=0D8ABC&color=fff';
                            ?>
                            <img src="<?php echo htmlspecialchars($img_src); ?>" class="profile-img mb-3" alt="Profile">
                            <h5 class="card-title fw-bold"><?php echo htmlspecialchars($musician['username']); ?></h5>
                            <p class="text-muted small mb-2">
                                <i class="fas fa-music me-1"></i> <?php echo htmlspecialchars($musician['genres']); ?> <br>
                                <span class="badge bg-secondary mt-1"><?php echo $musician['band_type'] === 'solo' ? 'ศิลปินเดี่ยว' : 'วงดนตรี'; ?></span>
                            </p>
                            <div class="star-rating mb-3">
                                <?php 
                                $score = $musician['rating_score'];
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($score >= $i) echo '<i class="fas fa-star"></i>';
                                    else if ($score >= $i - 0.5) echo '<i class="fas fa-star-half-alt"></i>';
                                    else echo '<i class="far fa-star"></i>';
                                }
                                ?>
                                <span class="text-dark ms-1 fw-bold"><?php echo number_format($score, 1); ?></span>
                            </div>
                            <a href="musician_profile.php?id=<?php echo $musician['user_id']; ?>" class="btn btn-outline-primary rounded-pill w-100">ดูโปรไฟล์</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <p class="text-center text-muted py-4">ยังไม่มีข้อมูลนักดนตรีในระบบ</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Posts Section -->
<div class="bg-light py-5">
    <div class="container">
        <h3 class="fw-bold mb-4"><i class="fas fa-bullhorn text-primary me-2"></i>อัปเดตและโปรโมทล่าสุด</h3>
        <div class="row">
            <?php if (count($recent_posts) > 0): ?>
                <?php foreach ($recent_posts as $post): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <?php 
                                    $img_src = !empty($post['profile_image']) && $post['profile_image'] !== 'default_avatar.png' ? 'uploads/avatars/' . $post['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($post['username']).'&background=0D8ABC&color=fff';
                                    ?>
                                    <img src="<?php echo htmlspecialchars($img_src); ?>" class="profile-img-small me-2" alt="Profile">
                                    <div>
                                        <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($post['username']); ?></h6>
                                        <small class="text-muted"><?php echo date('d M Y, H:i', strtotime($post['created_at'])); ?></small>
                                    </div>
                                </div>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <p class="text-center text-muted">ยังไม่มีโพสต์อัปเดต</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="text-center mt-3">
            <a href="feed.php" class="btn btn-primary rounded-pill px-4">ดูโพสต์ทั้งหมด</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
