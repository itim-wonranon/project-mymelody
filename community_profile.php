<?php
// community_profile.php
require_once 'includes/db.php';
session_start();

$username = $_GET['username'] ?? '';
if (empty($username)) {
    header("Location: community.php");
    exit();
}

// Fetch user profile
$stmt = $conn->prepare("
    SELECT u.*, cp.display_name as c_name, cp.community_username as c_user, cp.avatar as c_avatar, cp.bio, cp.banner,
           m.profile_image as m_img, e.profile_image as e_img
    FROM users u
    LEFT JOIN community_profiles cp ON u.id = cp.user_id
    LEFT JOIN musician_profiles m ON u.id = m.user_id
    LEFT JOIN employer_profiles e ON u.id = e.user_id
    WHERE cp.community_username = ? OR u.username = ?
    LIMIT 1
");
$stmt->execute([$username, $username]);
$profile_user = $stmt->fetch();

if (!$profile_user) {
    die("User not found");
}

$display_name = $profile_user['c_name'] ?: ($profile_user['first_name'] ? $profile_user['first_name'] . ' ' . $profile_user['last_name'] : $profile_user['username']);
$display_username = $profile_user['c_user'] ?: $profile_user['username'];
$avatar = $profile_user['c_avatar'] ?: ($profile_user['role'] === 'musician' ? $profile_user['m_img'] : $profile_user['e_img']);
$avatar_src = $avatar && $avatar !== 'default_avatar.png' ? 'uploads/avatars/' . $avatar : 'https://ui-avatars.com/api/?name=' . urlencode($display_name);
$banner_src = $profile_user['banner'] ? 'uploads/banners/' . $profile_user['banner'] : 'https://images.unsplash.com/photo-1514525253361-bee8718a300c?w=1200&h=400&fit=crop';

// Stats
$stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
$stmt->execute([$profile_user['id']]);
$posts_count = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM reposts WHERE user_id = ?");
$stmt->execute([$profile_user['id']]);
$reposts_count = $stmt->fetchColumn();

// Fetch current user community profile for comment input
$community_profile = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM community_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $community_profile = $stmt->fetch();
}
?>
<?php include 'includes/header.php'; ?>

    <div class="container py-4">
        <div class="community-layout">
            <!-- Left Sidebar -->
            <aside class="community-left">
                <?php 
                if (isset($_SESSION['user_id'])) {
                    // stats for sidebar
                    $stmt = $conn->prepare("
                        SELECT 
                            (SELECT COUNT(*) FROM posts WHERE user_id = ?) as posts_count,
                            (SELECT COUNT(*) FROM post_reactions pr JOIN posts p ON pr.post_id = p.id WHERE p.user_id = ?) as reactions_count
                    ");
                    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
                    $stats = $stmt->fetch();
                    
                    include 'includes/community_sidebar.php'; 
                }
                ?>
            </aside>

            <!-- Main Content -->
            <main class="community-main">
                <!-- Profile Header Card -->
                <div class="glass-card p-0 overflow-hidden mb-4 border border-secondary border-opacity-25 shadow-lg animate__animated animate__fadeIn">
                    <div class="profile-banner" style="height: 220px; background: url('<?php echo $banner_src; ?>') center/cover; position: relative;">
                        <div class="position-absolute bottom-0 start-0 w-100 p-4 bg-gradient-to-t-dark">
                        </div>
                    </div>
                    <div class="px-4 pb-4 position-relative">
                        <div class="profile-avatar-container" style="margin-top: -70px; display: inline-block;">
                            <img src="<?php echo $avatar_src; ?>" class="rounded-circle border border-4 border-dark shadow-glow" style="width: 140px; height: 140px; object-fit: cover; background: #1a1a1a;">
                        </div>
                        
                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h3 class="fw-bold mb-0 text-white"><?php echo htmlspecialchars($display_name); ?></h3>
                                    <p class="text-secondary mb-3">@<?php echo htmlspecialchars($display_username); ?></p>
                                </div>
                                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_user['id']): ?>
                                    <a href="community_profile_setup.php" class="btn btn-outline-primary btn-sm rounded-pill px-4 shadow-sm hover-glow">
                                        <i class="fas fa-user-edit me-2"></i>แก้ไขโปรไฟล์
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <p class="text-light mb-4" style="line-height: 1.6;"><?php echo nl2br(htmlspecialchars($profile_user['bio'] ?? 'ยังไม่มีคำอธิบายโปรไฟล์ในขณะนี้...')); ?></p>
                            
                            <div class="d-flex gap-5 text-center border-top border-secondary border-opacity-25 pt-4">
                                <div class="stat-item">
                                    <h5 class="mb-0 fw-bold text-white"><?php echo number_format($posts_count); ?></h5>
                                    <small class="text-secondary text-uppercase tracking-wider" style="font-size: 0.7rem;">โพสต์</small>
                                </div>
                                <div class="stat-item">
                                    <h5 class="mb-0 fw-bold text-white"><?php echo number_format($reposts_count); ?></h5>
                                    <small class="text-secondary text-uppercase tracking-wider" style="font-size: 0.7rem;">รีโพสต์</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Tabs -->
                <?php $current_tab = $_GET['tab'] ?? 'all'; ?>
                <div class="d-flex gap-2 mb-4 animate__animated animate__fadeIn">
                    <a href="community_profile.php?username=<?php echo htmlspecialchars($display_username); ?>&tab=all" 
                       class="profile-tab <?php echo $current_tab === 'all' ? 'active' : ''; ?> px-4 py-2 rounded-pill">
                       โพสต์ทั้งหมด
                    </a>
                    <a href="community_profile.php?username=<?php echo htmlspecialchars($display_username); ?>&tab=reposts" 
                       class="profile-tab <?php echo $current_tab === 'reposts' ? 'active' : ''; ?> px-4 py-2 rounded-pill">
                       รีโพสต์
                    </a>
                </div>

                <!-- Posts List -->
                <div class="profile-posts-feed animate__animated animate__fadeInUp">
                    <?php
                    // Adjust query based on tab
                    $sql = "";
                    if ($current_tab === 'reposts') {
                        $sql = "
                            SELECT p.*, u.username, u.role, u.first_name, u.last_name, 
                                    cp.display_name as community_name, cp.community_username, cp.avatar as community_avatar,
                                    m.profile_image as m_img, m.band_type, m.band_members, e.profile_image as e_img,
                                    (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id) as reactions_total,
                                    (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count,
                                    r.created_at as sort_time, ? as reposted_by
                             FROM reposts r
                             JOIN posts p ON r.post_id = p.id
                             JOIN users u ON p.user_id = u.id
                             LEFT JOIN community_profiles cp ON u.id = cp.user_id
                             LEFT JOIN musician_profiles m ON u.id = m.user_id
                             LEFT JOIN employer_profiles e ON u.id = e.user_id
                             WHERE r.user_id = ?
                             ORDER BY sort_time DESC
                        ";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$display_name, $profile_user['id']]);
                    } else {
                        $sql = "
                            (SELECT p.*, u.username, u.role, u.first_name, u.last_name, 
                                    cp.display_name as community_name, cp.community_username, cp.avatar as community_avatar,
                                    m.profile_image as m_img, m.band_type, m.band_members, e.profile_image as e_img,
                                    (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id) as reactions_total,
                                    (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count,
                                    p.created_at as sort_time, NULL as reposted_by
                             FROM posts p
                             JOIN users u ON p.user_id = u.id
                             LEFT JOIN community_profiles cp ON u.id = cp.user_id
                             LEFT JOIN musician_profiles m ON u.id = m.user_id
                             LEFT JOIN employer_profiles e ON u.id = e.user_id
                             WHERE p.user_id = ?)
                            UNION
                            (SELECT p.*, u.username, u.role, u.first_name, u.last_name, 
                                    cp.display_name as community_name, cp.community_username, cp.avatar as community_avatar,
                                    m.profile_image as m_img, m.band_type, m.band_members, e.profile_image as e_img,
                                    (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id) as reactions_total,
                                    (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count,
                                    r.created_at as sort_time, ? as reposted_by
                             FROM reposts r
                             JOIN posts p ON r.post_id = p.id
                             JOIN users u ON p.user_id = u.id
                             LEFT JOIN community_profiles cp ON u.id = cp.user_id
                             LEFT JOIN musician_profiles m ON u.id = m.user_id
                             LEFT JOIN employer_profiles e ON u.id = e.user_id
                             WHERE r.user_id = ?)
                            ORDER BY sort_time DESC
                        ";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$profile_user['id'], $display_name, $profile_user['id']]);
                    }
                    $posts = $stmt->fetchAll();

                    if (empty($posts)) {
                        echo '<div class="text-center py-5 text-muted glass-card border-secondary border-opacity-25">';
                        echo '<i class="fas fa-stream fa-3x mb-3 opacity-25"></i>';
                        echo '<h5>ยังไม่มีความเคลื่อนไหว</h5>';
                        echo '<p class="small mb-0">ผู้ใช้งานนี้ยังไม่ได้โพสต์หรือรีโพสต์ใดๆ</p>';
                        echo '</div>';
                    } else {
                        foreach ($posts as $post) {
                            if ($post['reposted_by']) {
                                echo '<div class="repost-indicator mb-2 animate__animated animate__fadeIn">';
                                echo '  <i class="fas fa-retweet me-2"></i>' . htmlspecialchars($post['reposted_by']) . ' ได้รีโพสต์';
                                echo '</div>';
                            }
                            include 'includes/community_post_card.php';
                        }
                    }
                    ?>
                </div>
            </main>

            <!-- Right Sidebar -->
            <aside class="community-right">
                <div class="sticky-top" style="top: 80px; z-index: 1;">
                    <div class="glass-card p-4 mb-4 border border-secondary border-opacity-25">
                        <h5 class="fw-bold text-white mb-3"><i class="fas fa-bolt text-warning me-2"></i>กำลังมาแรง</h5>
                        <div class="trending-list">
                            <?php include 'includes/community_trending.php'; ?>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
