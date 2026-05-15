<?php
require_once 'includes/db.php';
session_start();

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

// 4. Fetch Community Posts for the Pulse section
$stmt = $conn->prepare("
    SELECT p.content, p.created_at, u.username, u.role, m.profile_image as m_img, e.profile_image as e_img
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN musician_profiles m ON u.id = m.user_id
    LEFT JOIN employer_profiles e ON u.id = e.user_id
    ORDER BY p.created_at DESC
    LIMIT 8
");
$stmt->execute();
$pulse_posts = $stmt->fetchAll();

?>
<?php include 'includes/header.php'; ?>

<!-- Modern Hero Section -->
<div class="hero-section" style="background-image: url('images/hero_bg.png');">
    <div class="hero-overlay"></div>
    <div class="hero-content container text-center py-5">
        <h1 class="display-title text-white mb-2 animate__animated animate__fadeInDown">MY MELODY</h1>
        <p class="fs-4 text-gradient mb-5 animate__animated animate__fadeInUp">Crafting the perfect stage for every talent.</p>
        
        <!-- Mixer-style Search -->
        <div class="animate__animated animate__zoomIn">
            <form action="search.php" method="GET" class="mixer-search">
                <i class="fas fa-search text-secondary me-3"></i>
                <input type="text" name="q" placeholder="ค้นหาศิลปิน, วงดนตรี หรือแนวเพลงที่คุณชอบ...">
                <button type="submit" class="search-btn">
                    <i class="fas fa-play"></i>
                </button>
            </form>
            <div class="mt-4 d-flex justify-content-center gap-2 flex-wrap">
                <span class="badge rounded-pill bg-dark border border-secondary px-3 py-2 text-muted">Jazz</span>
                <span class="badge rounded-pill bg-dark border border-secondary px-3 py-2 text-muted">Rock</span>
                <span class="badge rounded-pill bg-dark border border-secondary px-3 py-2 text-muted">Acoustic</span>
                <span class="badge rounded-pill bg-dark border border-secondary px-3 py-2 text-muted">Pop</span>
                <span class="badge rounded-pill bg-dark border border-secondary px-3 py-2 text-muted">EDM</span>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    
    <!-- Module 2: Become Musician (Stage Invitation) -->
    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'employer'): ?>
        <div class="musician-cta-section mb-5 p-5">
            <div class="cta-pattern"></div>
            <div class="row align-items-center">
                <div class="col-lg-7 position-relative">
                    <span class="badge bg-primary px-3 py-2 mb-3 rounded-pill text-uppercase fw-bold">Open for Musicians</span>
                    <h2 class="display-5 fw-800 text-white mb-3">ก้าวขึ้นสู่ <span class="text-primary">เวทีระดับโลก</span> ของเรา</h2>
                    <p class="lead text-secondary mb-4">เปลี่ยนความสามารถของคุณให้เป็นรายได้ และเชื่อมต่อกับโอกาสที่คาดไม่ถึง</p>
                    <a href="become_musician.php" class="btn btn-primary btn-lg rounded-pill px-5 py-3 glow-btn fw-bold">
                        สมัครเป็นนักดนตรีตอนนี้ <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
                <div class="col-lg-5 d-none d-lg-block text-center position-relative">
                    <div class="floating-music-icons">
                        <i class="fas fa-music fa-3x" style="top: 10%; left: 20%;"></i>
                        <i class="fas fa-guitar fa-3x" style="bottom: 15%; right: 10%;"></i>
                        <i class="fas fa-microphone fa-2x" style="top: 40%; right: 30%;"></i>
                    </div>
                    <img src="https://img.icons8.com/clouds/500/guitar.png" alt="Guitar" class="img-fluid" style="max-height: 300px; filter: drop-shadow(0 0 20px var(--primary-color));">
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Artist Showcase Helper -->
    <?php
    function renderArtistCardModern($musicians) {
        if (count($musicians) > 0) {
            foreach ($musicians as $musician) {
                $img_src = !empty($musician['profile_image']) && $musician['profile_image'] !== 'default_avatar.png' ? 'uploads/avatars/' . $musician['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($musician['username']).'&background=c471ed&color=fff';
                ?>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="artist-card-modern text-center">
                        <div class="artist-img-wrapper">
                            <img src="<?php echo htmlspecialchars($img_src); ?>" alt="Artist">
                            <div class="artist-status"></div>
                        </div>
                        <h4 class="fw-bold text-white mb-1"><?php echo htmlspecialchars($musician['username']); ?></h4>
                        <p class="text-primary small fw-bold mb-3"><?php echo htmlspecialchars($musician['genres']); ?></p>
                        
                        <div class="star-rating mb-4">
                            <?php 
                            $score = $musician['rating_score'] ?? 0;
                            for ($i = 1; $i <= 5; $i++) {
                                if ($score >= $i) echo '<i class="fas fa-star"></i>';
                                else if ($score >= $i - 0.5) echo '<i class="fas fa-star-half-alt"></i>';
                                else echo '<i class="far fa-star"></i>';
                            }
                            ?>
                        </div>
                        
                        <a href="musician_profile.php?id=<?php echo $musician['user_id']; ?>" class="btn btn-outline-primary rounded-pill w-100 py-2">ดูโปรไฟล์</a>
                    </div>
                </div>
                <?php
            }
        }
    }
    ?>

    <!-- Module 1: Artist Showcases -->
    <div class="mb-5">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h6 class="text-primary fw-bold text-uppercase" style="letter-spacing: 2px; font-size: 0.8rem;">Recommended</h6>
                <h2 class="fw-bold text-white display-6">ศิลปินแนะนำ</h2>
            </div>
            <a href="search.php" class="text-muted text-decoration-none hover-glow mb-2">ดูทั้งหมด <i class="fas fa-chevron-right ms-1"></i></a>
        </div>
        <div class="row">
            <?php renderArtistCardModern($recommended_musicians); ?>
        </div>
    </div>

    <!-- Community Pulse Section (Full Width) -->
    <div class="my-5 py-5 border-top border-bottom border-secondary">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h6 class="text-warning fw-bold text-uppercase" style="letter-spacing: 2px; font-size: 0.8rem;">Community Pulse</h6>
                <h2 class="fw-bold text-white display-6">กระแสตอบรับล่าสุด</h2>
            </div>
            <a href="feed.php" class="btn btn-sm btn-outline-warning rounded-pill px-4">เข้าสู่คอมมูนิตี้</a>
        </div>
        
        <div class="community-pulse-container custom-scrollbar">
            <?php if (count($pulse_posts) > 0): ?>
                <?php foreach ($pulse_posts as $post): ?>
                    <div class="pulse-card">
                        <div class="d-flex align-items-center mb-3">
                            <?php 
                            $img = $post['role'] === 'musician' ? $post['m_img'] : $post['e_img'];
                            $img_src = !empty($img) && $img !== 'default_avatar.png' ? 'uploads/avatars/' . $img : 'https://ui-avatars.com/api/?name='.urlencode($post['username']).'&background=00f0ff&color=000';
                            ?>
                            <img src="<?php echo htmlspecialchars($img_src); ?>" class="profile-img-small me-3" alt="User">
                            <div>
                                <h6 class="mb-0 fw-bold text-white"><?php echo htmlspecialchars($post['username']); ?></h6>
                                <small class="text-secondary small" style="font-size: 0.7rem;"><?php echo date('d M, H:i', strtotime($post['created_at'])); ?></small>
                            </div>
                        </div>
                        <p class="text-light opacity-75 mb-0" style="font-size: 0.9rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-5 text-center w-100 text-muted">
                    <i class="far fa-comment-dots fa-3x mb-3"></i>
                    <p>ยังไม่มีการอัปเดตในคอมมูนิตี้</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- New & Active Artists -->
    <div class="row mt-5">
        <div class="col-lg-6 mb-4 border-end border-secondary">
            <h3 class="fw-bold text-white mb-4"><i class="fas fa-seedling text-warning me-2"></i>ศิลปินใหม่</h3>
            <div class="row">
                <?php 
                foreach (array_slice($new_musicians, 0, 2) as $musician): 
                    $img_src = !empty($musician['profile_image']) && $musician['profile_image'] !== 'default_avatar.png' ? 'uploads/avatars/' . $musician['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($musician['username']).'&background=c471ed&color=fff';
                ?>
                <div class="col-md-6 mb-4">
                    <div class="glass-card p-4 text-center h-100 hover-glow">
                        <img src="<?php echo htmlspecialchars($img_src); ?>" class="profile-img mb-3 shadow" style="width: 80px; height: 80px; border-width: 2px;">
                        <h5 class="fw-bold text-white mb-1"><?php echo htmlspecialchars($musician['username']); ?></h5>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($musician['genres']); ?></p>
                        <a href="musician_profile.php?id=<?php echo $musician['user_id']; ?>" class="btn btn-sm btn-primary rounded-pill w-100">โปรไฟล์</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-lg-6 mb-4 ps-lg-5">
            <h3 class="fw-bold text-white mb-4"><i class="fas fa-broadcast-tower text-info me-2"></i>ศิลปินที่กำลังเคลื่อนไหว</h3>
            <div class="row">
                <?php 
                foreach (array_slice($active_musicians, 0, 2) as $musician): 
                    $img_src = !empty($musician['profile_image']) && $musician['profile_image'] !== 'default_avatar.png' ? 'uploads/avatars/' . $musician['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($musician['username']).'&background=c471ed&color=fff';
                ?>
                <div class="col-md-6 mb-4">
                    <div class="glass-card p-4 text-center h-100 hover-glow">
                        <img src="<?php echo htmlspecialchars($img_src); ?>" class="profile-img mb-3 shadow" style="width: 80px; height: 80px; border-width: 2px;">
                        <h5 class="fw-bold text-white mb-1"><?php echo htmlspecialchars($musician['username']); ?></h5>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($musician['genres']); ?></p>
                        <a href="musician_profile.php?id=<?php echo $musician['user_id']; ?>" class="btn btn-sm btn-primary rounded-pill w-100">โปรไฟล์</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
