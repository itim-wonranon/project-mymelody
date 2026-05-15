<?php
// community_notifications.php
require_once 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Mark all as read
$stmt = $conn->prepare("UPDATE community_notifications SET is_read = 1 WHERE user_id = ?");
$stmt->execute([$user_id]);

// Fetch Notifications
$stmt = $conn->prepare("
    SELECT n.*, u.username, 
           cp.display_name as sender_name, cp.avatar as sender_avatar,
           p.content as post_preview
    FROM community_notifications n
    JOIN users u ON n.sender_id = u.id
    LEFT JOIN community_profiles cp ON u.id = cp.user_id
    JOIN posts p ON n.post_id = p.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 50
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

// Fetch Profile & Stats for Sidebar
$stmt = $conn->prepare("SELECT * FROM community_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$community_profile = $stmt->fetch();

$stmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM posts WHERE user_id = ?) as posts_count,
        (SELECT COUNT(*) FROM post_reactions pr JOIN posts p ON pr.post_id = p.id WHERE p.user_id = ?) as reactions_count
");
$stmt->execute([$user_id, $user_id]);
$stats = $stmt->fetch();

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="community-layout">
        <!-- Left Sidebar -->
        <aside class="community-left">
            <?php include 'includes/community_sidebar.php'; ?>
        </aside>

        <!-- Main Content -->
        <main class="community-main">
            <div class="glass-card p-4 mb-4 animate__animated animate__fadeIn">
                <h4 class="fw-bold text-white mb-1"><i class="fas fa-bell text-warning me-2"></i>การแจ้งเตือน</h4>
                <p class="text-secondary small mb-0">ติดตามความเคลื่อนไหวในโพสต์ของคุณ</p>
            </div>

            <div class="notifications-list animate__animated animate__fadeIn">
                <?php if (empty($notifications)): ?>
                    <div class="glass-card p-5 text-center text-muted">
                        <i class="fas fa-bell-slash fa-3x mb-3 opacity-25"></i>
                        <p>ยังไม่มีการแจ้งเตือนในขณะนี้</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                        <div class="glass-card p-3 mb-3 hover-glow <?php echo $n['is_read'] ? 'opacity-75' : 'border-primary'; ?>">
                            <div class="d-flex align-items-center">
                                <img src="uploads/avatars/<?php echo htmlspecialchars($n['sender_avatar'] ?? 'default_avatar.png'); ?>" 
                                     class="post-input-avatar me-3" style="width: 45px; height: 45px;">
                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="text-white small">
                                        <span class="fw-bold"><?php echo htmlspecialchars($n['sender_name'] ?? $n['username']); ?></span>
                                        <?php 
                                        if ($n['type'] === 'reaction') echo ' แสดงความรู้สึกกับโพสต์ของคุณ';
                                        elseif ($n['type'] === 'comment') echo ' ตอบกลับโพสต์ของคุณ';
                                        elseif ($n['type'] === 'repost') echo ' รีโพสต์เนื้อหาของคุณ';
                                        ?>
                                    </div>
                                    <div class="text-secondary text-truncate small italic opacity-50 mt-1" style="font-size: 0.75rem;">
                                        "<?php echo htmlspecialchars(mb_substr($n['post_preview'], 0, 50)); ?>..."
                                    </div>
                                    <small class="text-muted" style="font-size: 0.65rem;">
                                        <?php echo date('d M Y, H:i', strtotime($n['created_at'])); ?>
                                    </small>
                                </div>
                                <a href="community_profile.php?username=<?php echo $_SESSION['username']; ?>&post_id=<?php echo $n['post_id']; ?>" 
                                   class="btn btn-sm btn-outline-primary rounded-pill px-3 ms-2" style="font-size: 0.7rem;">ดูโพสต์</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>

        <!-- Right Sidebar -->
        <aside class="community-right">
            <?php include 'includes/community_trending.php'; ?>
        </aside>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
