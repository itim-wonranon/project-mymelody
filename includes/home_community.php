<?php
// includes/home_community.php

// Fetch Recent Promoted Posts for the Community section
$stmt = $conn->prepare("
    SELECT p.content, p.created_at, u.username, u.role, m.profile_image as m_img, e.profile_image as e_img
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN musician_profiles m ON u.id = m.user_id
    LEFT JOIN employer_profiles e ON u.id = e.user_id
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_posts = $stmt->fetchAll();
?>

<!-- Sidebar Community Section -->
<div class="community-sidebar sticky-top" style="top: 100px; z-index: 10;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-white mb-1"><i class="fas fa-bullhorn text-primary me-2"></i>Community</h4>
            <p class="text-secondary small mb-0">อัปเดตล่าสุด</p>
        </div>
        <a href="feed.php" class="text-primary text-decoration-none small hover-glow">ดูทั้งหมด <i class="fas fa-arrow-right"></i></a>
    </div>
    
    <div class="d-flex flex-column gap-3">
        <?php if (count($recent_posts) > 0): ?>
            <?php foreach ($recent_posts as $post): ?>
                <div class="card border-secondary shadow-sm hover-glow" style="background: rgba(21, 21, 28, 0.7); backdrop-filter: blur(10px); transition: all 0.3s ease;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center mb-2">
                            <?php 
                            $img = $post['role'] === 'musician' ? $post['m_img'] : $post['e_img'];
                            $img_src = !empty($img) && $img !== 'default_avatar.png' ? 'uploads/avatars/' . $img : 'https://ui-avatars.com/api/?name='.urlencode($post['username']).'&background=00f0ff&color=000';
                            ?>
                            <img src="<?php echo htmlspecialchars($img_src); ?>" class="profile-img-small me-2 shadow-sm border border-secondary" alt="Profile" style="width: 35px; height: 35px;">
                            <div>
                                <h6 class="mb-0 fw-bold text-white text-truncate" style="max-width: 150px; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($post['username']); ?>
                                    <?php if ($post['role'] === 'musician'): ?>
                                        <i class="fas fa-music text-primary ms-1" style="font-size: 0.6rem;" title="นักดนตรี"></i>
                                    <?php endif; ?>
                                </h6>
                                <small class="text-secondary" style="font-size: 0.7rem;"><i class="far fa-clock me-1"></i><?php echo date('d M, H:i', strtotime($post['created_at'])); ?></small>
                            </div>
                        </div>
                        <p class="card-text text-light opacity-75 mb-0" style="line-height: 1.4; font-size: 0.85rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card bg-transparent border-secondary border-dashed text-center p-4">
                <p class="text-muted small mb-0"><i class="far fa-comment-dots me-2"></i>ยังไม่มีโพสต์อัปเดต</p>
            </div>
        <?php endif; ?>
    </div>
</div>
