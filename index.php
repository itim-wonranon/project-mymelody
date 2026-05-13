<?php
require_once 'includes/db.php';
session_start();

$success = '';
$error = '';



// 1. Fetch Recommended Artists (By Rating)
$stmt = $conn->prepare("
    SELECT u.id as user_id, u.username, m.profile_image, m.band_type, m.genres, m.rate, m.rating_score
    FROM users u
    JOIN musician_profiles m ON u.id = m.user_id
    WHERE u.role = 'musician' AND m.is_verified = 1
    ORDER BY m.rating_score DESC
    LIMIT 4
");
$stmt->execute();
$recommended_musicians = $stmt->fetchAll();

// 2. Fetch New Artists (By Created At)
$stmt = $conn->prepare("
    SELECT u.id as user_id, u.username, m.profile_image, m.band_type, m.genres, m.rate, m.rating_score
    FROM users u
    JOIN musician_profiles m ON u.id = m.user_id
    WHERE u.role = 'musician'
    ORDER BY u.created_at DESC
    LIMIT 4
");
$stmt->execute();
$new_musicians = $stmt->fetchAll();

// 3. Fetch Recently Active Artists (By Latest Post)
$stmt = $conn->prepare("
    SELECT u.id as user_id, u.username, m.profile_image, m.band_type, m.genres, m.rate, m.rating_score, MAX(p.created_at) as last_active
    FROM users u
    JOIN musician_profiles m ON u.id = m.user_id
    JOIN posts p ON u.id = p.user_id
    WHERE u.role = 'musician'
    GROUP BY u.id
    ORDER BY last_active DESC
    LIMIT 4
");
$stmt->execute();
$active_musicians = $stmt->fetchAll();

// Fetch Recent Promoted Posts
$stmt = $conn->prepare("
    SELECT p.content, p.created_at, u.username, u.role, m.profile_image as m_img, e.profile_image as e_img
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN musician_profiles m ON u.id = m.user_id
    LEFT JOIN employer_profiles e ON u.id = e.user_id
    ORDER BY p.created_at DESC
    LIMIT 6
");
$stmt->execute();
$recent_posts = $stmt->fetchAll();
?>
<?php include 'includes/header.php'; ?>

<!-- Hero Section -->
<div class="hero-section">
    <div class="hero-overlay"></div>
    <div class="hero-content container text-center text-white py-5">
        <h1 class="display-3 fw-bold mb-3" style="text-shadow: 0 4px 15px rgba(0,0,0,0.8);">MUSE CONNECT</h1>
        <p class="lead mb-5 fs-4" style="text-shadow: 0 2px 10px rgba(0,0,0,0.8); color: var(--secondary-color);">
            ค้นหานักดนตรีอิสระที่ใช่ สำหรับงานของคุณอย่างง่ายดายและปลอดภัย
        </p>
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <form action="search.php" method="GET" class="search-container shadow-lg">
                    <input type="text" name="q" class="form-control" placeholder="ค้นหาตามชื่อวง, แนวเพลง หรือสถานที่...">
                    <button type="submit" class="btn btn-primary rounded-pill px-4 ms-2">
                        <i class="fas fa-search me-2"></i>ค้นหาศิลปิน
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="container py-5 mt-3">
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Community Showcase -->
    <div class="mb-5 pb-5 border-bottom border-secondary" style="border-color: #2a2a35 !important;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-white"><i class="fas fa-bullhorn text-primary me-2"></i>Community <span class="text-secondary fs-5 fw-normal ms-2">คอมมูนิตี้และอัปเดตล่าสุด</span></h3>
            <a href="feed.php" class="btn btn-outline-primary rounded-pill px-4">ดูโพสต์ทั้งหมด</a>
        </div>
        
        <div class="row">
            <?php if (count($recent_posts) > 0): ?>
                <?php foreach ($recent_posts as $post): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 border-secondary shadow-sm hover-glow" style="background: #15151c; transition: all 0.3s ease;">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-3">
                                    <?php 
                                    $img = $post['role'] === 'musician' ? $post['m_img'] : $post['e_img'];
                                    $img_src = !empty($img) && $img !== 'default_avatar.png' ? 'uploads/avatars/' . $img : 'https://ui-avatars.com/api/?name='.urlencode($post['username']).'&background=00f0ff&color=000';
                                    ?>
                                    <img src="<?php echo htmlspecialchars($img_src); ?>" class="profile-img-small me-3 shadow-sm border border-secondary" alt="Profile">
                                    <div>
                                        <h6 class="mb-0 fw-bold text-white text-truncate" style="max-width: 200px;">
                                            <?php echo htmlspecialchars($post['username']); ?>
                                            <?php if ($post['role'] === 'musician'): ?>
                                                <i class="fas fa-music text-primary ms-1" style="font-size: 0.7rem;" title="นักดนตรี"></i>
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-secondary" style="font-size: 0.75rem;"><i class="far fa-clock me-1"></i><?php echo date('d M Y, H:i', strtotime($post['created_at'])); ?></small>
                                    </div>
                                </div>
                                <p class="card-text text-light opacity-75" style="line-height: 1.6; font-size: 1rem; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden;"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <div class="card bg-transparent border-secondary border-dashed text-center p-5">
                        <p class="text-muted fs-5 mb-0"><i class="far fa-comment-dots me-2"></i>ยังไม่มีโพสต์อัปเดตในคอมมูนิตี้</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Artist Category Renderer Function (Inline helper) -->
    <?php
    function renderArtistCards($musicians, $emptyMessage) {
        if (count($musicians) > 0) {
            echo '<div class="row">';
            foreach ($musicians as $musician) {
                $img_src = !empty($musician['profile_image']) && $musician['profile_image'] !== 'default_avatar.png' ? 'uploads/avatars/' . $musician['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($musician['username']).'&background=c471ed&color=fff';
                ?>
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center p-4">
                            <div class="position-relative d-inline-block mb-3">
                                <img src="<?php echo htmlspecialchars($img_src); ?>" class="profile-img shadow-lg" alt="Profile" style="width: 110px; height: 110px;">
                            </div>
                            <h5 class="card-title fw-bold text-white mb-1 text-truncate"><?php echo htmlspecialchars($musician['username']); ?></h5>
                            <p class="text-muted small mb-2 text-truncate">
                                <?php echo htmlspecialchars($musician['genres']); ?>
                            </p>
                            <span class="badge bg-dark border border-secondary mb-3 text-uppercase" style="font-size: 0.7rem;"><?php echo $musician['band_type'] === 'solo' ? 'ศิลปินเดี่ยว' : 'วงดนตรี'; ?></span>
                            <div class="star-rating mb-3 fs-6">
                                <?php 
                                $score = $musician['rating_score'] ?? 0;
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($score >= $i) echo '<i class="fas fa-star"></i>';
                                    else if ($score >= $i - 0.5) echo '<i class="fas fa-star-half-alt"></i>';
                                    else echo '<i class="far fa-star"></i>';
                                }
                                ?>
                            </div>
                            <a href="musician_profile.php?id=<?php echo $musician['user_id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill w-100">ดูโปรไฟล์</a>
                        </div>
                    </div>
                </div>
                <?php
            }
            echo '</div>';
        } else {
            echo '<div class="card bg-transparent border-secondary border-dashed text-center p-4 mb-4"><p class="text-muted mb-0">'.$emptyMessage.'</p></div>';
        }
    }
    ?>

    <!-- 1. Recommended Artists -->
    <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
        <h3 class="fw-bold text-white"><span class="text-primary"><i class="fas fa-fire me-2"></i>ศิลปินแนะนำ</span> <span class="fs-6 text-muted fw-normal ms-2">เรียงตามคะแนนรีวิวสูงสุด</span></h3>
        <a href="search.php" class="text-decoration-none text-muted hover-glow">ดูทั้งหมด <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
    <?php renderArtistCards($recommended_musicians, "ยังไม่มีศิลปินแนะนำ"); ?>

    <!-- 2. New Artists -->
    <div class="d-flex justify-content-between align-items-center mb-4 mt-5">
        <h3 class="fw-bold text-white"><span class="text-warning"><i class="fas fa-seedling me-2"></i>ศิลปินใหม่</span> <span class="fs-6 text-muted fw-normal ms-2">นักดนตรีที่เพิ่งเข้าร่วมแพลตฟอร์ม</span></h3>
    </div>
    <?php renderArtistCards($new_musicians, "ยังไม่มีศิลปินหน้าใหม่"); ?>

    <!-- 3. Recently Active Artists -->
    <div class="d-flex justify-content-between align-items-center mb-4 mt-5">
        <h3 class="fw-bold text-white"><span class="text-info"><i class="fas fa-broadcast-tower me-2"></i>ศิลปินอัปเดทล่าสุด</span> <span class="fs-6 text-muted fw-normal ms-2">นักดนตรีที่มีการเคลื่อนไหวล่าสุด</span></h3>
    </div>
    <?php renderArtistCards($active_musicians, "ยังไม่มีความเคลื่อนไหวจากศิลปิน"); ?>

</div>

<?php include 'includes/footer.php'; ?>
