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
    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'employer'): ?>
        <div class="card border-0 shadow-lg mb-5 hover-glow" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 20px;">
            <div class="card-body p-5 text-center text-white">
                <h2 class="fw-bold mb-3"><i class="fas fa-guitar fa-lg me-3"></i>คุณคือนักดนตรีใช่ไหม?</h2>
                <p class="fs-5 mb-4 opacity-75">เพิ่มโอกาสในการรับงานของคุณโดยการสมัครเป็นนักดนตรีบนแพลตฟอร์มของเรา</p>
                <a href="become_musician.php" class="btn btn-light btn-lg rounded-pill px-5 fw-bold text-primary shadow-sm">
                    สมัครเป็นนักดนตรีตอนนี้ <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>
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

    </div>

    <div class="container py-5">
        <div class="row">
            <!-- Left Main Content (Artists) -->
            <div class="col-lg-8 pe-lg-5">

    <!-- Artist Category Renderer Function (Inline helper) -->
    <?php
    function renderArtistCards($musicians, $emptyMessage) {
        if (count($musicians) > 0) {
            echo '<div class="row">';
            foreach ($musicians as $musician) {
                $img_src = !empty($musician['profile_image']) && $musician['profile_image'] !== 'default_avatar.png' ? 'uploads/avatars/' . $musician['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($musician['username']).'&background=c471ed&color=fff';
                ?>
                <div class="col-md-6 mb-4">
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

            </div> <!-- End Left Main Content -->

            <!-- Right Sidebar (Community) -->
            <div class="col-lg-4 mt-5 mt-lg-0">
                <?php include 'includes/home_community.php'; ?>
            </div>
        </div> <!-- End Row -->
</div>

<?php include 'includes/footer.php'; ?>
