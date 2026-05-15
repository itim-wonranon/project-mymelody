<?php
// includes/community_post_card.php
$post_user_display_name = !empty($post['community_name']) ? $post['community_name'] : $post['username'];
$post_user_username = !empty($post['community_username']) ? $post['community_username'] : $post['username'];
$post_avatar = !empty($post['community_avatar']) ? $post['community_avatar'] : ($post['role'] === 'musician' ? $post['m_img'] : $post['e_img']);

$post_avatar_src = !empty($post_avatar) && $post_avatar !== 'default_avatar.png' ? 'uploads/avatars/' . $post_avatar : 'https://ui-avatars.com/api/?name=' . urlencode($post['username']) . '&background=c471ed&color=fff';

// If no community name, use the logic we implemented earlier for musicians
if (empty($post['community_name']) && $post['role'] === 'musician') {
    if ($post['band_type'] === 'solo') {
        if (!empty($post['first_name'])) {
            $post_user_display_name = $post['first_name'] . ' ' . $post['last_name'];
        }
    } else {
        $band_data = json_decode($post['band_members'], true);
        if (!empty($band_data['band_name'])) {
            $post_user_display_name = $band_data['band_name'];
        }
    }
}
?>

<div class="community-post-card animate__animated animate__fadeInUp">
    <!-- Post Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center">
            <img src="<?php echo htmlspecialchars($post_avatar_src); ?>" class="post-input-avatar me-3 shadow-sm" style="width: 50px; height: 50px;">
            <div>
                <h6 class="mb-0 fw-bold text-white">
                    <?php echo htmlspecialchars($post_user_display_name); ?>
                    <?php if ($post['role'] === 'musician'): ?>
                        <span class="badge bg-primary ms-1" style="font-size: 0.6rem;">นักดนตรี</span>
                    <?php elseif ($post['role'] === 'employer'): ?>
                        <span class="badge bg-success ms-1" style="font-size: 0.6rem;">ผู้ว่าจ้าง</span>
                    <?php elseif ($post['role'] === 'admin'): ?>
                        <span class="badge bg-danger ms-1" style="font-size: 0.6rem;">ผู้ดูแลระบบ</span>
                    <?php endif; ?>
                </h6>
                <small class="text-secondary">@<?php echo htmlspecialchars($post_user_username); ?> • <?php echo date('d M Y, H:i', strtotime($post['created_at'])); ?></small>
            </div>
        </div>
        <div class="dropdown">
            <button class="btn btn-link text-secondary p-0" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
            <ul class="dropdown-menu dropdown-menu-dark">
                <li><a class="dropdown-item" href="#"><i class="fas fa-flag me-2"></i>รายงานโพสต์</a></li>
            </ul>
        </div>
    </div>

    <!-- Post Content -->
    <div class="post-body mb-3">
        <?php if (!empty($post['content'])): ?>
            <p class="text-white fs-5" style="white-space: pre-wrap;"><?php echo htmlspecialchars($post['content']); ?></p>
        <?php endif; ?>
        
        <?php if ($post['feeling']): ?>
            <div class="mb-3"><span class="badge bg-dark text-warning border border-warning rounded-pill px-3 py-2"><i class="fas fa-smile me-2"></i>กำลังรู้สึก <?php echo htmlspecialchars($post['feeling']); ?></span></div>
        <?php endif; ?>

        <?php if ($post['type'] === 'image' && $post['media_url']): ?>
            <img src="uploads/community/<?php echo $post['media_url']; ?>" class="img-fluid rounded-4 shadow mb-3">
        <?php elseif ($post['type'] === 'video' && $post['media_url']): ?>
            <video src="uploads/community/<?php echo $post['media_url']; ?>" class="w-100 rounded-4 shadow mb-3" controls></video>
        <?php elseif ($post['type'] === 'poll' && $post['poll_options']): ?>
            <div class="poll-card p-3 border border-secondary rounded-4 bg-dark bg-opacity-25 mb-3">
                <h6 class="fw-bold text-white mb-3"><?php echo htmlspecialchars($post['poll_question']); ?></h6>
                <?php 
                $options = json_decode($post['poll_options'], true);
                foreach ($options as $idx => $opt): 
                ?>
                    <button class="btn btn-outline-secondary w-100 text-start mb-2 rounded-pill px-4" onclick="votePoll(<?php echo $post['id']; ?>, <?php echo $idx; ?>)">
                        <?php echo htmlspecialchars($opt); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Post Interactions -->
    <div class="post-footer pt-3 border-top border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
        <div class="d-flex gap-3">
            <div class="reaction-container">
                <button class="action-btn px-0" id="reaction-btn-<?php echo $post['id']; ?>" onclick="reactPost(<?php echo $post['id']; ?>, 'like')">
                    <i class="far fa-thumbs-up me-2"></i> <span>ชอบ</span>
                </button>
                <div class="reaction-popup shadow-glow">
                    <span class="reaction-icon" title="ชอบ" onclick="reactPost(<?php echo $post['id']; ?>, 'like')">👍</span>
                    <span class="reaction-icon" title="หัวใจ" onclick="reactPost(<?php echo $post['id']; ?>, 'heart')">❤️</span>
                    <span class="reaction-icon" title="ขำ" onclick="reactPost(<?php echo $post['id']; ?>, 'haha')">😆</span>
                    <span class="reaction-icon" title="ว้าว" onclick="reactPost(<?php echo $post['id']; ?>, 'wow')">😮</span>
                    <span class="reaction-icon" title="เศร้า" onclick="reactPost(<?php echo $post['id']; ?>, 'sad')">😢</span>
                    <span class="reaction-icon" title="โมโห" onclick="reactPost(<?php echo $post['id']; ?>, 'angry')">😡</span>
                </div>
            </div>
            <button class="action-btn px-0" onclick="toggleComments(<?php echo $post['id']; ?>)">
                <i class="far fa-comment me-2"></i> <span><?php echo $post['comments_count']; ?> ความเห็น</span>
            </button>
        </div>
        <div class="reactions-count-<?php echo $post['id']; ?> small text-secondary">
            <?php if ($post['reactions_total'] > 0): ?>
                <i class="fas fa-heart text-danger"></i> <?php echo $post['reactions_total']; ?> คนรู้สึกชอบ
            <?php endif; ?>
        </div>
    </div>

    <!-- Comments Section (Hidden by default) -->
    <div id="comments-<?php echo $post['id']; ?>" class="comments-section mt-4 d-none">
        <div class="comment-input-area d-flex gap-2 mb-3">
            <img src="uploads/avatars/<?php echo htmlspecialchars($community_profile['avatar'] ?? 'default_avatar.png'); ?>" class="post-input-avatar" style="width: 35px; height: 35px;">
            <div class="input-group">
                <input type="text" id="comment-input-<?php echo $post['id']; ?>" class="form-control form-control-sm bg-dark border-secondary text-white rounded-pill px-3" placeholder="เขียนความคิดเห็น...">
                <button class="btn btn-sm btn-primary rounded-pill ms-2 px-3" onclick="submitComment(<?php echo $post['id']; ?>)">ส่ง</button>
            </div>
        </div>
        <div id="comments-list-<?php echo $post['id']; ?>">
            <!-- AJAX will load comments here -->
        </div>
    </div>
</div>
