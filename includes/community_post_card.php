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

<div class="community-post-card" id="post-<?php echo $post['id']; ?>">
    <!-- Post Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center">
            <a href="community_profile.php?username=<?php echo htmlspecialchars($post_user_username); ?>" class="profile-link-img">
                <img src="<?php echo htmlspecialchars($post_avatar_src); ?>" class="post-input-avatar me-3 shadow-sm" style="width: 50px; height: 50px;">
            </a>
            <div>
                <h6 class="mb-0 fw-bold text-white">
                    <a href="community_profile.php?username=<?php echo htmlspecialchars($post_user_username); ?>" class="profile-link-name">
                        <?php echo htmlspecialchars($post_user_display_name); ?>
                    </a>
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
            <button class="btn btn-link text-secondary p-0" data-bs-toggle="dropdown">
                <i class="fas fa-ellipsis-h"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow-glow">
                <?php if ($post['user_id'] == $_SESSION['user_id']): ?>
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="editPost(<?php echo $post['id']; ?>)"><i class="fas fa-edit me-2"></i>แก้ไขโพสต์</a></li>
                    <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deletePost(<?php echo $post['id']; ?>)"><i class="fas fa-trash me-2"></i>ลบโพสต์</a></li>
                <?php else: ?>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-flag me-2"></i>รายงานโพสต์</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Post Content -->
    <div class="post-body mb-3">
        <?php if (!empty($post['content'])): ?>
            <!-- Edit Mode (Hidden) -->
            <div id="post-edit-box-<?php echo $post['id']; ?>" class="mb-3 d-none">
                <textarea class="form-control bg-dark text-white border-secondary mb-2" id="post-edit-field-<?php echo $post['id']; ?>" rows="3"><?php echo htmlspecialchars($post['content']); ?></textarea>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-sm rounded-pill px-3" onclick="savePostEdit(<?php echo $post['id']; ?>)">บันทึก</button>
                    <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="cancelPostEdit(<?php echo $post['id']; ?>)">ยกเลิก</button>
                </div>
            </div>
            <?php 
                $content_display = htmlspecialchars($post['content']);
                // Regex for hashtags including Thai characters
                $content_display = preg_replace('/#([^\s#]+)/u', '<a href="community.php?search=%23$1" class="hashtag-link">#$1</a>', $content_display);
            ?>
            <p class="text-white fs-5" id="post-content-<?php echo $post['id']; ?>" style="white-space: pre-wrap;"><?php echo $content_display; ?></p>
        <?php endif; ?>
        
        <?php if ($post['feeling'] || $post['location']): ?>
            <div class="mb-3 d-flex flex-wrap gap-2">
                <?php if ($post['feeling']): ?>
                    <span class="badge bg-dark text-warning border border-warning rounded-pill px-3 py-2"><i class="fas fa-smile me-2"></i>กำลังรู้สึก <?php echo htmlspecialchars($post['feeling']); ?></span>
                <?php endif; ?>
                <?php if ($post['location']): ?>
                    <span class="badge bg-dark text-danger border border-danger rounded-pill px-3 py-2"><i class="fas fa-map-marker-alt me-2"></i>อยู่ที่ <?php echo htmlspecialchars($post['location']); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($post['type'] === 'image' && $post['media_url']): ?>
            <img src="uploads/community/<?php echo $post['media_url']; ?>" class="img-fluid rounded-4 shadow mb-3">
        <?php elseif ($post['type'] === 'video' && $post['media_url']): ?>
            <video src="uploads/community/<?php echo $post['media_url']; ?>" class="w-100 rounded-4 shadow mb-3" controls></video>
        <?php elseif ($post['type'] === 'poll' && $post['poll_options']): ?>
            <div class="poll-card p-3 border border-secondary rounded-4 bg-dark bg-opacity-25 mb-3 poll-card-container-<?php echo $post['id']; ?>">
                <h6 class="fw-bold text-white mb-3"><?php echo htmlspecialchars($post['poll_question']); ?></h6>
                <?php 
                $options = json_decode($post['poll_options'], true);
                
                // Get votes summary
                $stmt = $conn->prepare("SELECT option_index, COUNT(*) as c FROM poll_votes WHERE post_id = ? GROUP BY option_index");
                $stmt->execute([$post['id']]);
                $vote_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                $stmt = $conn->prepare("SELECT COUNT(*) FROM poll_votes WHERE post_id = ?");
                $stmt->execute([$post['id']]);
                $total_v = $stmt->fetchColumn();

                $stmt = $conn->prepare("SELECT option_index FROM poll_votes WHERE post_id = ? AND user_id = ?");
                $stmt->execute([$post['id'], $_SESSION['user_id'] ?? 0]);
                $user_vote = $stmt->fetchColumn();
                $has_voted = ($user_vote !== false);

                foreach ($options as $idx => $opt): 
                    $opt_count = $vote_data[$idx] ?? 0;
                    $percent = $total_v > 0 ? round(($opt_count / $total_v) * 100) : 0;
                ?>
                    <button class="poll-btn btn btn-outline-secondary w-100 text-start mb-2 rounded-pill px-4 position-relative overflow-hidden <?php echo $has_voted ? 'voted' : ''; ?> <?php echo ($has_voted && $user_vote == $idx) ? 'active-choice' : ''; ?>" 
                            onclick="votePoll(<?php echo $post['id']; ?>, <?php echo $idx; ?>)"
                            data-label="<?php echo htmlspecialchars($opt); ?>">
                        <div class="d-flex justify-content-between align-items-center w-100 position-relative" style="z-index: 2;">
                            <span><?php echo htmlspecialchars($opt); ?></span>
                            <?php if ($has_voted): ?>
                                <span><?php echo $percent; ?>%</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($has_voted): ?>
                            <div class="poll-progress" style="width: <?php echo $percent; ?>%;"></div>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
                <div class="text-secondary small mt-2 text-end poll-total-count"><?php echo $total_v; ?> โหวต</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Post Interactions -->
    <div class="post-footer pt-3 border-top border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
        <div class="d-flex gap-3">
            <?php 
            require_once 'includes/reaction_summary.php'; 
            // Check user's own reaction for button state
            $own_stmt = $conn->prepare("SELECT reaction_type FROM post_reactions WHERE post_id = ? AND user_id = ?");
            $own_stmt->execute([$post['id'], $_SESSION['user_id'] ?? 0]);
            $own_react = $own_stmt->fetch();
            
            // Check if user has reposted
            $repost_stmt = $conn->prepare("SELECT id FROM reposts WHERE post_id = ? AND user_id = ?");
            $repost_stmt->execute([$post['id'], $_SESSION['user_id'] ?? 0]);
            $has_reposted = $repost_stmt->fetch();
            
            // Get total reposts
            $total_reposts_stmt = $conn->prepare("SELECT COUNT(*) FROM reposts WHERE post_id = ?");
            $total_reposts_stmt->execute([$post['id']]);
            $total_reposts = $total_reposts_stmt->fetchColumn();
            ?>
            <div class="reaction-container">
                <button class="action-btn px-0" id="reaction-btn-<?php echo $post['id']; ?>" 
                        onclick="reactPost(<?php echo $post['id']; ?>, 'like')"
                        data-current="<?php echo $own_react ? $own_react['reaction_type'] : 'none'; ?>">
                    <?php if ($own_react): ?>
                        <span class="text-primary fw-bold"><?php echo getBtnText($own_react['reaction_type']); ?></span>
                    <?php else: ?>
                        <i class="far fa-thumbs-up me-2"></i> <span>ถูกใจ</span>
                    <?php endif; ?>
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

            <!-- Repost Button -->
            <button class="action-btn px-0 <?php echo $has_reposted ? 'text-success fw-bold' : ''; ?>" 
                    id="repost-btn-<?php echo $post['id']; ?>" 
                    onclick="repostPost(<?php echo $post['id']; ?>)">
                <i class="fas fa-retweet me-2"></i> <span class="repost-count-<?php echo $post['id']; ?>"><?php echo $total_reposts; ?> รีโพสต์</span>
            </button>

            <button class="action-btn px-0" onclick="toggleComments(<?php echo $post['id']; ?>)">
                <i class="far fa-comment me-2"></i> <span class="comment-count-label-<?php echo $post['id']; ?>"><?php echo $post['comments_count']; ?> ความเห็น</span>
            </button>
        </div>
        <?php renderReactionSummary($conn, $post['id'], 'post_reactions', 'reactions-count-' . $post['id']); ?>
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
