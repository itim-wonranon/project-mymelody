<?php
// includes/community_sidebar.php
?>
<div class="community-sidebar-content">
    <div class="glass-card p-3 mb-4">
        <?php 
        $c_avatar = $community_profile['avatar'] ?? 'default_avatar.png';
        $c_name = $community_profile['display_name'] ?? $_SESSION['username'];
        $c_username = $community_profile['community_username'] ?? $_SESSION['username'];
        
        $current_page = basename($_SERVER['PHP_SELF']);
        $is_profile_page = ($current_page === 'community_profile.php');
        $viewing_username = $_GET['username'] ?? '';
        $is_own_profile = ($is_profile_page && $viewing_username === $c_username);
        ?>
        <a href="community_profile.php?username=<?php echo htmlspecialchars($c_username); ?>" 
           class="sidebar-profile-card <?php echo $is_own_profile ? 'active' : ''; ?>">
            <div class="d-flex align-items-center">
                <img src="uploads/avatars/<?php echo htmlspecialchars($c_avatar); ?>" class="post-input-avatar shadow-sm me-3">
                <div class="overflow-hidden">
                    <h6 class="mb-0 fw-bold text-white text-truncate"><?php echo htmlspecialchars($c_name); ?></h6>
                    <small class="text-secondary">@<?php echo htmlspecialchars($c_username); ?></small>
                </div>
            </div>
        </a>
        <div class="row g-2 text-center border-top border-secondary pt-3 mt-2">
            <div class="col-6 border-end border-secondary">
                <h6 class="mb-0 fw-bold text-white"><?php echo $stats['posts_count'] ?? 0; ?></h6>
                <small class="text-secondary" style="font-size: 0.7rem;">โพสต์</small>
            </div>
            <div class="col-6">
                <h6 class="mb-0 fw-bold text-white"><?php echo $stats['reactions_count'] ?? 0; ?></h6>
                <small class="text-secondary" style="font-size: 0.7rem;">การตอบรับ</small>
            </div>
        </div>
    </div>

        <?php 
        $current_filter = $_GET['filter'] ?? ''; 
        $current_search = $_GET['search'] ?? '';
        $is_community_home = ($current_page === 'community.php' && $current_filter === '' && $current_search === '');
        ?>
        <a href="community.php" class="community-nav-link <?php echo $is_community_home ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> <span>ฟีดข่าวสาร</span>
        </a>
        <a href="community.php?filter=popular" class="community-nav-link <?php echo $current_filter == 'popular' ? 'active' : ''; ?>">
            <i class="fas fa-fire"></i> <span>ยอดนิยม</span>
        </a>
        <a href="community.php?filter=trending" class="community-nav-link <?php echo $current_filter == 'trending' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> <span>กำลังมาแรง</span>
        </a>
        <a href="community.php?filter=events" class="community-nav-link <?php echo $current_filter == 'events' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i> <span>กิจกรรม</span>
        </a>
        <a href="community_notifications.php" class="community-nav-link <?php echo $current_page === 'community_notifications.php' ? 'active' : ''; ?>">
            <i class="fas fa-bell"></i> <span>แจ้งเตือน</span>
            <?php 
            $stmt = $conn->prepare("SELECT COUNT(*) FROM community_notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$_SESSION['user_id']]);
            $notif_count = $stmt->fetchColumn();
            if ($notif_count > 0): 
            ?>
                <span class="badge bg-danger rounded-pill ms-auto"><?php echo $notif_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="community_profile_setup.php" class="community-nav-link">
            <i class="fas fa-user-cog"></i> <span>ตั้งค่าโปรไฟล์</span>
        </a>
    </nav>
</div>
