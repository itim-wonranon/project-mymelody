<?php
require_once 'includes/db.php';
session_start();

// 1. Fetch Recommended Artists (By Rating)
$stmt = $conn->prepare("
    SELECT u.id as user_id, u.username, u.first_name, u.last_name, m.profile_image, m.band_type, m.band_members, m.genres, m.rate, m.rating_score
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
    SELECT u.id as user_id, u.username, u.first_name, u.last_name, m.profile_image, m.band_type, m.band_members, m.genres, m.rate, m.rating_score
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
    SELECT u.id as user_id, u.username, u.first_name, u.last_name, m.profile_image, m.band_type, m.band_members, m.genres, m.rate, m.rating_score, MAX(p.created_at) as last_active
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

// 4. Fetch Community Posts (Latest 4)
$stmt = $conn->prepare("
    SELECT p.*, u.username, u.first_name, u.last_name, u.role, 
           m.profile_image as m_img, m.band_type, m.band_members, e.profile_image as e_img,
           cp.display_name as community_name, cp.avatar as community_avatar, cp.community_username,
           (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id) as reactions_total,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_total
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN musician_profiles m ON u.id = m.user_id
    LEFT JOIN employer_profiles e ON u.id = e.user_id
    LEFT JOIN community_profiles cp ON u.id = cp.user_id
    ORDER BY p.created_at DESC
    LIMIT 4
");
$stmt->execute();
$latest_posts = $stmt->fetchAll();

// 5. Fetch Top Posts (Top 4 by Reactions)
$stmt = $conn->prepare("
    SELECT p.*, u.username, u.first_name, u.last_name, u.role, 
           m.profile_image as m_img, m.band_type, m.band_members, e.profile_image as e_img,
           cp.display_name as community_name, cp.avatar as community_avatar, cp.community_username,
           (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id) as reactions_total,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_total
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN musician_profiles m ON u.id = m.user_id
    LEFT JOIN employer_profiles e ON u.id = e.user_id
    LEFT JOIN community_profiles cp ON u.id = cp.user_id
    ORDER BY reactions_total DESC, p.created_at DESC
    LIMIT 4
");
$stmt->execute();
$top_posts = $stmt->fetchAll();

?>
<?php include 'includes/header.php'; ?>

<!-- Modern Hero Section -->
<div class="hero-section" style="background-image: url('images/hero_bg.png');">
    <div class="hero-overlay"></div>
    <div class="hero-content container text-center py-5">
        <h1 class="display-title text-white mb-2 animate__animated animate__fadeInDown">MY MELODY</h1>
        <p class="fs-4 text-gradient mb-5 animate__animated animate__fadeInUp">Crafting the perfect stage for every
            talent</p>

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
                <a href="search.php?q=Jazz"
                    class="badge rounded-pill bg-dark border border-secondary px-3 py-2 text-muted text-decoration-none hover-glow">Jazz</a>
                <a href="search.php?q=Rock"
                    class="badge rounded-pill bg-dark border border-secondary px-3 py-2 text-muted text-decoration-none hover-glow">Rock</a>
                <a href="search.php?q=Acoustic"
                    class="badge rounded-pill bg-dark border border-secondary px-3 py-2 text-muted text-decoration-none hover-glow">Acoustic</a>
                <a href="search.php?q=Pop"
                    class="badge rounded-pill bg-dark border border-secondary px-3 py-2 text-muted text-decoration-none hover-glow">Pop</a>
                <a href="search.php?q=EDM"
                    class="badge rounded-pill bg-dark border border-secondary px-3 py-2 text-muted text-decoration-none hover-glow">EDM</a>
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
                    <span class="badge bg-primary px-3 py-2 mb-3 rounded-pill text-uppercase fw-bold">Open for
                        Musicians</span>
                    <h2 class="display-5 fw-800 text-white mb-3">ก้าวขึ้นสู่ <span class="text-primary">เวทีระดับโลก</span>
                        ของเรา</h2>
                    <p class="lead text-secondary mb-4">เปลี่ยนความสามารถของคุณให้เป็นรายได้
                        และเชื่อมต่อกับโอกาสที่คาดไม่ถึง</p>
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
                    <img src="https://img.icons8.com/clouds/500/guitar.png" alt="Guitar" class="img-fluid"
                        style="max-height: 300px; filter: drop-shadow(0 0 20px var(--primary-color));">
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Artist Showcase Helper -->
    <?php
    function renderArtistCardModern($musicians)
    {
        if (count($musicians) > 0) {
            foreach ($musicians as $musician) {
                $img_src = !empty($musician['profile_image']) && $musician['profile_image'] !== 'default_avatar.png' ? 'uploads/avatars/' . $musician['profile_image'] : 'https://ui-avatars.com/api/?name=' . urlencode($musician['username']) . '&background=c471ed&color=fff';
                ?>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="artist-card-modern text-center">
                        <div class="artist-img-wrapper mb-3">
                            <img src="<?php echo htmlspecialchars($img_src); ?>" class="avatar-lg shadow" alt="Artist">
                            <div class="artist-status"></div>
                        </div>
                        <?php 
                        $display_name = $musician['username'];
                        if ($musician['band_type'] === 'solo') {
                            if (!empty($musician['first_name'])) {
                                $display_name = $musician['first_name'] . ' ' . $musician['last_name'];
                            }
                        } else {
                            $band_data = json_decode($musician['band_members'], true);
                            if (!empty($band_data['band_name'])) {
                                $display_name = $band_data['band_name'];
                            }
                        }
                        ?>
                        <h4 class="fw-bold text-white mb-1"><?php echo htmlspecialchars($display_name); ?></h4>
                        <p class="text-primary small fw-bold mb-3"><?php echo htmlspecialchars($musician['genres']); ?></p>

                        <div class="star-rating mb-4">
                            <?php
                            $score = $musician['rating_score'] ?? 0;
                            for ($i = 1; $i <= 5; $i++) {
                                if ($score >= $i)
                                    echo '<i class="fas fa-star"></i>';
                                else if ($score >= $i - 0.5)
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                else
                                    echo '<i class="far fa-star"></i>';
                            }
                            ?>
                        </div>

                        <a href="musician_profile.php?id=<?php echo $musician['user_id']; ?>"
                            class="btn btn-outline-primary rounded-pill w-100 py-2">ดูโปรไฟล์</a>
                    </div>
                </div>
                <?php
            }
        }
    }
    ?>

    <!-- Module 1: Artist Showcases -->
    <div class="mb-5 pb-5 border-bottom border-secondary border-opacity-25">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h6 class="text-primary fw-bold text-uppercase" style="letter-spacing: 2px; font-size: 0.8rem;">
                    Recommended</h6>
                <h2 class="fw-bold text-white display-6">ศิลปินแนะนำ</h2>
            </div>
            <a href="search.php" class="text-muted text-decoration-none hover-glow mb-2">ดูทั้งหมด <i
                    class="fas fa-chevron-right ms-1"></i></a>
        </div>
        <div class="row">
            <?php renderArtistCardModern($recommended_musicians); ?>
        </div>
    </div>

    <!-- Module 2: New Artists (Full Width) -->
    <div class="mb-5 py-4 pb-5 border-bottom border-secondary border-opacity-25">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h6 class="text-warning fw-bold text-uppercase" style="letter-spacing: 2px; font-size: 0.8rem;">
                    New Arrival</h6>
                <h2 class="fw-bold text-white display-6">ศิลปินใหม่</h2>
            </div>
            <a href="search.php?sort=newest" class="text-muted text-decoration-none hover-glow mb-2">ดูทั้งหมด <i
                    class="fas fa-chevron-right ms-1"></i></a>
        </div>
        <div class="row">
            <?php renderArtistCardModern($new_musicians); ?>
        </div>
    </div>

    <!-- Module 3: Active Artists (Full Width) -->
    <div class="mb-5 py-4 pb-5 border-bottom border-secondary border-opacity-25">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h6 class="text-info fw-bold text-uppercase" style="letter-spacing: 2px; font-size: 0.8rem;">
                    Active Now</h6>
                <h2 class="fw-bold text-white display-6">ศิลปินที่กำลังเคลื่อนไหว</h2>
            </div>
            <a href="search.php?sort=active" class="text-muted text-decoration-none hover-glow mb-2">ดูทั้งหมด <i
                    class="fas fa-chevron-right ms-1"></i></a>
        </div>
        <div class="row">
            <?php renderArtistCardModern($active_musicians); ?>
        </div>
    </div>

    <!-- Community Pulse Section (Compact Static View) - Moved to bottom -->
    <div class="my-5 py-5 border-top border-bottom border-secondary">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h6 class="text-warning fw-bold text-uppercase" style="letter-spacing: 2px; font-size: 0.7rem;">Community Pulse</h6>
                <h3 class="fw-bold text-white">ความเคลื่อนไหวในคอมมูนิตี้</h3>
            </div>
            <a href="community.php" class="btn btn-sm btn-outline-warning rounded-pill px-4" style="font-size: 0.75rem;">เข้าสู่คอมมูนิตี้</a>
        </div>

        <ul class="nav nav-pills mb-4 gap-2" id="pulseTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill px-4 py-1 small border border-secondary border-opacity-25" id="trending-tab" data-bs-toggle="pill" data-bs-target="#trending" type="button" role="tab" style="font-size: 0.75rem;">ยอดฮิต (Top 4)</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4 py-1 small border border-secondary border-opacity-25" id="latest-tab" data-bs-toggle="pill" data-bs-target="#latest" type="button" role="tab" style="font-size: 0.75rem;">ล่าสุด (Latest 4)</button>
            </li>
        </ul>

        <div class="tab-content" id="pulseTabsContent">
            <div class="tab-pane fade show active" id="trending" role="tabpanel">
                <div class="row g-3">
                    <?php if (count($top_posts) > 0): ?>
                        <?php foreach ($top_posts as $post): ?>
                            <div class="col-md-3">
                                <?php renderPulseCard($post); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: echo '<div class="col-12 text-muted small text-center py-4">ยังไม่มีข้อมูล</div>'; endif; ?>
                </div>
            </div>
            <div class="tab-pane fade" id="latest" role="tabpanel">
                <div class="row g-3">
                    <?php if (count($latest_posts) > 0): ?>
                        <?php foreach ($latest_posts as $post): ?>
                            <div class="col-md-3">
                                <?php renderPulseCard($post); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: echo '<div class="col-12 text-muted small text-center py-4">ยังไม่มีข้อมูล</div>'; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    function renderPulseCard($post) {
        global $conn; // Access DB for poll info
        $display_name = !empty($post['community_name']) ? $post['community_name'] : $post['username'];
        if (empty($post['community_name']) && $post['role'] === 'musician') {
            if ($post['band_type'] === 'solo') {
                if (!empty($post['first_name'])) {
                    $display_name = $post['first_name'] . ' ' . $post['last_name'];
                }
            } else {
                $band_data = json_decode($post['band_members'], true);
                if (!empty($band_data['band_name'])) {
                    $display_name = $band_data['band_name'];
                }
            }
        }
        $p_username = $post['community_username'] ?? $post['username'];
        $img = !empty($post['community_avatar']) ? $post['community_avatar'] : ($post['role'] === 'musician' ? $post['m_img'] : $post['e_img']);
        $avatar_src = !empty($img) && $img !== 'default_avatar.png' ? 'uploads/avatars/' . $img : 'https://ui-avatars.com/api/?name=' . urlencode($post['username']) . '&background=c471ed&color=fff';
        ?>
        <div class="pulse-card animate__animated animate__fadeIn" 
             style="min-width: 300px; padding: 15px; cursor: pointer;" 
             onclick="location.href='community.php?post_id=<?php echo $post['id']; ?>#post-<?php echo $post['id']; ?>'">
            <div class="d-flex align-items-center mb-2">
                <a href="community_profile.php?username=<?php echo htmlspecialchars($p_username); ?>" class="profile-link-img" onclick="event.stopPropagation();">
                    <img src="<?php echo htmlspecialchars($avatar_src); ?>" class="avatar-sm shadow-sm">
                </a>
                <div class="overflow-hidden">
                    <h6 class="mb-0 fw-bold text-white small text-truncate" style="max-width: 150px;">
                        <a href="community_profile.php?username=<?php echo htmlspecialchars($p_username); ?>" class="text-white text-decoration-none" onclick="event.stopPropagation();">
                            <?php echo htmlspecialchars($display_name); ?>
                        </a>
                    </h6>
                    <small class="text-secondary" style="font-size: 0.6rem;">@<?php echo htmlspecialchars($p_username); ?></small>
                </div>
                <?php if ($post['reactions_total'] > 10): ?>
                    <span class="ms-auto badge bg-danger" style="font-size: 0.5rem;"><i class="fas fa-fire"></i></span>
                <?php endif; ?>
            </div>

            <!-- Content Area -->
            <div class="pulse-body mb-2">
                <?php if ($post['feeling'] || $post['location']): ?>
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <?php if ($post['feeling']): ?>
                            <span class="badge bg-dark text-warning border border-warning" style="font-size: 0.55rem; padding: 2px 8px;"><i class="fas fa-smile me-1"></i><?php echo htmlspecialchars($post['feeling']); ?></span>
                        <?php endif; ?>
                        <?php if ($post['location']): ?>
                            <span class="badge bg-dark text-danger border border-danger" style="font-size: 0.55rem; padding: 2px 8px;"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($post['location']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <p class="text-light opacity-75 mb-2" style="font-size: 0.8rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                    <?php echo htmlspecialchars($post['content']); ?>
                </p>

                <?php if ($post['type'] === 'image' && $post['media_url']): ?>
                    <div class="rounded-3 overflow-hidden mb-2" style="height: 120px;">
                        <img src="uploads/community/<?php echo $post['media_url']; ?>" class="w-100 h-100" style="object-fit: cover;">
                    </div>
                <?php elseif ($post['type'] === 'video' && $post['media_url']): ?>
                    <div class="rounded-3 overflow-hidden mb-2 bg-black d-flex align-items-center justify-content-center" style="height: 120px;">
                        <i class="fas fa-play-circle text-white fa-2x opacity-50"></i>
                    </div>
                <?php elseif ($post['type'] === 'poll' && $post['poll_question']): ?>
                    <div class="p-2 border border-secondary rounded-3 bg-dark bg-opacity-25 mb-2">
                        <small class="text-info fw-bold" style="font-size: 0.65rem;"><i class="fas fa-poll me-1"></i>POLL</small>
                        <div class="text-white small text-truncate mt-1" style="font-size: 0.75rem;"><?php echo htmlspecialchars($post['poll_question']); ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="pulse-footer d-flex justify-content-between align-items-center pt-2 border-top border-secondary border-opacity-10">
                <div class="d-flex gap-2 small" style="font-size: 0.7rem;">
                    <span class="text-secondary"><i class="fas fa-heart text-danger me-1"></i> <?php echo $post['reactions_total']; ?></span>
                    <span class="text-secondary"><i class="fas fa-comment text-primary me-1"></i> <?php echo $post['comments_total']; ?></span>
                </div>
                <small class="text-secondary" style="font-size: 0.6rem;"><?php echo date('d M, H:i', strtotime($post['created_at'])); ?></small>
            </div>
        </div>
        <?php
    }
    ?>

</div>

<?php include 'includes/footer.php'; ?>