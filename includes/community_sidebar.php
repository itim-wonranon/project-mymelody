<?php
// includes/community_sidebar.php
?>
<div class="community-sidebar-content">
    <div class="glass-card p-3 mb-4">
        <div class="d-flex align-items-center mb-3">
            <?php 
            $c_avatar = $community_profile['avatar'] ?? 'default_avatar.png';
            $c_name = $community_profile['display_name'] ?? $_SESSION['username'];
            $c_username = $community_profile['community_username'] ?? $_SESSION['username'];
            ?>
            <img src="uploads/avatars/<?php echo htmlspecialchars($c_avatar); ?>" class="post-input-avatar me-3 shadow-sm">
            <div class="overflow-hidden">
                <h6 class="mb-0 fw-bold text-white text-truncate"><?php echo htmlspecialchars($c_name); ?></h6>
                <small class="text-secondary">@<?php echo htmlspecialchars($c_username); ?></small>
            </div>
        </div>
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

    <nav class="mb-4">
        <a href="community.php" class="community-nav-link active">
            <i class="fas fa-home"></i> <span>ฟีดข่าวสาร</span>
        </a>
        <a href="community.php?filter=popular" class="community-nav-link">
            <i class="fas fa-fire"></i> <span>ยอดนิยม</span>
        </a>
        <a href="community.php?filter=trending" class="community-nav-link">
            <i class="fas fa-chart-line"></i> <span>กำลังมาแรง</span>
        </a>
        <a href="community.php?filter=events" class="community-nav-link">
            <i class="fas fa-calendar-alt"></i> <span>กิจกรรม</span>
        </a>
        <a href="community_profile_setup.php" class="community-nav-link">
            <i class="fas fa-user-cog"></i> <span>ตั้งค่าโปรไฟล์</span>
        </a>
    </nav>
</div>
