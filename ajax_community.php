<?php
// ajax_community.php
require_once 'includes/db.php';
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    if (($_GET['action'] ?? '') !== 'get_comments') {
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
}

$user_id = $_SESSION['user_id'] ?? null;
$action = $_GET['action'] ?? '';

// Helper to get reaction summary
function getReactionSummary($conn, $id, $table) {
    $column = ($table === 'post_reactions') ? 'post_id' : 'comment_id';
    
    // Total count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM $table WHERE $column = ?");
    $stmt->execute([$id]);
    $total = $stmt->fetch()['total'];

    // Top 3 reaction types
    $stmt = $conn->prepare("SELECT reaction_type, COUNT(*) as c FROM $table WHERE $column = ? GROUP BY reaction_type ORDER BY c DESC LIMIT 3");
    $stmt->execute([$id]);
    $top = $stmt->fetchAll();

    // User's own reaction
    $own = null;
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT reaction_type FROM $table WHERE $column = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $res = $stmt->fetch();
        $own = $res ? $res['reaction_type'] : null;
    }

    return ['total' => $total, 'top' => $top, 'own' => $own];
}

function getReactionIcon($type) {
    switch ($type) {
        case 'heart': return '❤️';
        case 'haha': return '😆';
        case 'wow': return '😮';
        case 'sad': return '😢';
        case 'angry': return '😡';
        default: return '👍';
    }
}

function getReactionText($type) {
    switch ($type) {
        case 'heart': return 'รักเลย';
        case 'haha': return 'ฮ่าๆ';
        case 'wow': return 'ว้าว';
        case 'sad': return 'เศร้า';
        case 'angry': return 'โกรธ';
        case 'like': return 'ถูกใจ';
        default: return 'ชอบ';
    }
}

function addNotification($conn, $recipient_id, $sender_id, $type, $post_id) {
    if ($recipient_id == $sender_id) return; // Don't notify yourself
    $stmt = $conn->prepare("INSERT INTO community_notifications (user_id, sender_id, type, post_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$recipient_id, $sender_id, $type, $post_id]);
}

if ($action === 'react') {
    $post_id = $_POST['post_id'];
    $type = $_POST['type'];

    $stmt = $conn->prepare("SELECT id, reaction_type FROM post_reactions WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$user_id, $post_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['reaction_type'] === $type) {
            // Cancel reaction if same type
            $stmt = $conn->prepare("DELETE FROM post_reactions WHERE id = ?");
            $stmt->execute([$existing['id']]);
        } else {
            $stmt = $conn->prepare("UPDATE post_reactions SET reaction_type = ? WHERE id = ?");
            $stmt->execute([$type, $existing['id']]);
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO post_reactions (user_id, post_id, reaction_type) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $post_id, $type]);
        
        // Notify post owner
        $stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post_owner = $stmt->fetch();
        if ($post_owner) {
            addNotification($conn, $post_owner['user_id'], $user_id, 'reaction', $post_id);
        }
    }

    $summary = getReactionSummary($conn, $post_id, 'post_reactions');
    echo json_encode(['success' => true, 'summary' => $summary]);
}

if ($action === 'react_comment') {
    $comment_id = $_POST['comment_id'];
    $type = $_POST['type'];

    $stmt = $conn->prepare("SELECT id, reaction_type FROM comment_reactions WHERE user_id = ? AND comment_id = ?");
    $stmt->execute([$user_id, $comment_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['reaction_type'] === $type) {
            // Cancel reaction if same type
            $stmt = $conn->prepare("DELETE FROM comment_reactions WHERE id = ?");
            $stmt->execute([$existing['id']]);
        } else {
            $stmt = $conn->prepare("UPDATE comment_reactions SET reaction_type = ? WHERE id = ?");
            $stmt->execute([$type, $existing['id']]);
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO comment_reactions (user_id, comment_id, reaction_type) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $comment_id, $type]);
    }

    $summary = getReactionSummary($conn, $comment_id, 'comment_reactions');
    echo json_encode(['success' => true, 'summary' => $summary]);
}

if ($action === 'comment') {
    $post_id = $_POST['post_id'];
    $content = trim($_POST['content']);
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;

    if (!empty($content)) {
        $stmt = $conn->prepare("INSERT INTO comments (user_id, post_id, content, parent_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $post_id, $content, $parent_id]);
        
        // Notify post owner
        $stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post_owner = $stmt->fetch();
        if ($post_owner) {
            addNotification($conn, $post_owner['user_id'], $user_id, 'comment', $post_id);
        }

        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM comments WHERE post_id = ?");
        $stmt->execute([$post_id]);
        $count = $stmt->fetch()['total'];

        echo json_encode(['success' => true, 'count' => $count]);
    } else {
        echo json_encode(['error' => 'Empty content']);
    }
}

if ($action === 'edit_comment') {
    $comment_id = $_POST['comment_id'];
    $content = trim($_POST['content']);
    $stmt = $conn->prepare("UPDATE comments SET content = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$content, $comment_id, $user_id]);
    echo json_encode(['success' => true]);
}

if ($action === 'delete_comment') {
    $comment_id = $_POST['comment_id'];
    $stmt = $conn->prepare("SELECT post_id FROM comments WHERE id = ? AND user_id = ?");
    $stmt->execute([$comment_id, $user_id]);
    $comment = $stmt->fetch();
    if ($comment) {
        $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->execute([$comment_id]);
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM comments WHERE post_id = ?");
        $stmt->execute([$comment['post_id']]);
        $count = $stmt->fetch()['total'];
        echo json_encode(['success' => true, 'count' => $count, 'post_id' => $comment['post_id']]);
    } else {
        echo json_encode(['error' => 'Unauthorized']);
    }
}

if ($action === 'edit_post') {
    $post_id = $_POST['post_id'];
    $content = trim($_POST['content']);
    $stmt = $conn->prepare("UPDATE posts SET content = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$content, $post_id, $user_id]);
    echo json_encode(['success' => true]);
}

if ($action === 'delete_post') {
    $post_id = $_POST['post_id'];
    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
    $stmt->execute([$post_id, $user_id]);
    echo json_encode(['success' => true]);
}

if ($action === 'repost') {
    $post_id = $_POST['post_id'];
    $stmt = $conn->prepare("SELECT id FROM reposts WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$user_id, $post_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $conn->prepare("DELETE FROM reposts WHERE id = ?");
        $stmt->execute([$existing['id']]);
        $status = 'removed';
    } else {
        $stmt = $conn->prepare("INSERT INTO reposts (user_id, post_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $post_id]);
        $status = 'added';

        // Notify post owner
        $stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post_owner = $stmt->fetch();
        if ($post_owner) {
            addNotification($conn, $post_owner['user_id'], $user_id, 'repost', $post_id);
        }
    }

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM reposts WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $count = $stmt->fetch()['total'];

    echo json_encode(['success' => true, 'count' => $count, 'status' => $status]);
}

if ($action === 'get_comments') {
    $post_id = $_GET['post_id'];
    // Recursive query for threaded comments
    $stmt = $conn->prepare("
        SELECT c.*, u.username, u.first_name, u.last_name, u.role,
               cp.display_name as community_name, cp.community_username, cp.avatar as community_avatar,
               m.profile_image as m_img, e.profile_image as e_img, m.band_type, m.band_members
        FROM comments c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN community_profiles cp ON u.id = cp.user_id
        LEFT JOIN musician_profiles m ON u.id = m.user_id
        LEFT JOIN employer_profiles e ON u.id = e.user_id
        WHERE c.post_id = ? AND c.parent_id IS NULL
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$post_id]);
    $comments = $stmt->fetchAll();

    foreach ($comments as $comment) {
        renderComment($conn, $comment);
    }
}

function renderComment($conn, $comment, $is_reply = false) {
    $name = !empty($comment['community_name']) ? $comment['community_name'] : $comment['username'];
    $user_u = !empty($comment['community_username']) ? $comment['community_username'] : $comment['username'];
    $avatar = !empty($comment['community_avatar']) ? $comment['community_avatar'] : ($comment['role'] === 'musician' ? $comment['m_img'] : $comment['e_img']);
    $avatar_src = !empty($avatar) && $avatar !== 'default_avatar.png' ? 'uploads/avatars/' . $avatar : 'https://ui-avatars.com/api/?name=' . urlencode($comment['username']);

    if (empty($comment['community_name']) && $comment['role'] === 'musician') {
        if ($comment['band_type'] === 'solo') {
            if (!empty($comment['first_name'])) $name = $comment['first_name'] . ' ' . $comment['last_name'];
        } else {
            $bd = json_decode($comment['band_members'], true);
            if (!empty($bd['band_name'])) $name = $bd['band_name'];
        }
    }

    $summary = getReactionSummary($conn, $comment['id'], 'comment_reactions');
    ?>
    <div class="comment-item d-flex gap-2 mb-3 <?php echo $is_reply ? 'ms-5 border-start border-secondary ps-3' : ''; ?>">
        <img src="<?php echo htmlspecialchars($avatar_src); ?>" class="post-input-avatar" style="width: 35px; height: 35px;">
        <div class="comment-bubble flex-grow-1">
            <div class="d-flex justify-content-between align-items-start">
                <strong class="text-white small"><?php echo htmlspecialchars($name); ?> <span class="text-secondary fw-normal">@<?php echo htmlspecialchars($user_u); ?></span></strong>
                <div class="d-flex align-items-center gap-2">
                    <small class="text-secondary" style="font-size: 0.6rem;"><?php echo date('H:i, d M', strtotime($comment['created_at'])); ?></small>
                    <?php if ($comment['user_id'] == $_SESSION['user_id']): ?>
                    <div class="dropdown">
                        <button class="btn btn-link text-secondary p-0" data-bs-toggle="dropdown" style="font-size: 0.7rem;"><i class="fas fa-ellipsis-h"></i></button>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow" style="font-size: 0.75rem; min-width: 80px;">
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="editComment(<?php echo $comment['id']; ?>)"><i class="fas fa-edit me-2"></i>แก้ไข</a></li>
                            <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteComment(<?php echo $comment['id']; ?>)"><i class="fas fa-trash me-2"></i>ลบ</a></li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-light small mt-1" id="comment-content-<?php echo $comment['id']; ?>"><?php echo htmlspecialchars($comment['content']); ?></div>
            
            <!-- Edit Mode (Hidden) -->
            <div id="edit-box-<?php echo $comment['id']; ?>" class="mt-2 d-none">
                <textarea class="form-control form-control-sm bg-dark text-white border-secondary mb-2" id="edit-field-<?php echo $comment['id']; ?>"><?php echo htmlspecialchars($comment['content']); ?></textarea>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-sm rounded-pill px-3" onclick="saveEdit(<?php echo $comment['id']; ?>, <?php echo $comment['post_id']; ?>)">บันทึก</button>
                    <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="cancelEdit(<?php echo $comment['id']; ?>)">ยกเลิก</button>
                </div>
            </div>
            
            <div class="comment-actions d-flex align-items-center gap-3 mt-2">
                <!-- Comment Reactions -->
                <div class="reaction-container mini">
                    <button class="btn-link-secondary small text-decoration-none p-0 border-0 bg-transparent" 
                            id="comment-react-btn-<?php echo $comment['id']; ?>"
                            data-current="<?php echo $summary['own'] ?? 'none'; ?>"
                            onclick="reactComment(<?php echo $comment['id']; ?>, 'like')">
                        <?php echo $summary['own'] ? '<span class="text-primary fw-bold">' . getReactionText($summary['own']) . '</span>' : 'ถูกใจ'; ?>
                    </button>
                    <div class="reaction-popup mini">
                        <span onclick="reactComment(<?php echo $comment['id']; ?>, 'like')">👍</span>
                        <span onclick="reactComment(<?php echo $comment['id']; ?>, 'heart')">❤️</span>
                        <span onclick="reactComment(<?php echo $comment['id']; ?>, 'haha')">😆</span>
                        <span onclick="reactComment(<?php echo $comment['id']; ?>, 'wow')">😮</span>
                        <span onclick="reactComment(<?php echo $comment['id']; ?>, 'sad')">😢</span>
                        <span onclick="reactComment(<?php echo $comment['id']; ?>, 'angry')">😡</span>
                    </div>
                </div>

                <button class="btn-link-secondary small text-decoration-none p-0 border-0 bg-transparent" onclick="showReplyInput(<?php echo $comment['id']; ?>, <?php echo $comment['post_id']; ?>)">ตอบกลับ</button>

                <!-- Comment Stats -->
                <?php if ($summary['total'] > 0): ?>
                <div class="comment-stats ms-auto d-flex align-items-center gap-1" id="comment-react-stats-<?php echo $comment['id']; ?>"
                     style="cursor: pointer;" onclick="showReactions(<?php echo $comment['id']; ?>, 'comment')">
                    <div class="reaction-icons-small">
                        <?php foreach ($summary['top'] as $t): echo getReactionIcon($t['reaction_type']); endforeach; ?>
                    </div>
                    <span class="text-secondary" style="font-size: 0.7rem;"><?php echo $summary['total']; ?> คน</span>
                </div>
                <?php else: ?>
                <div class="comment-stats ms-auto d-flex align-items-center gap-1 d-none" id="comment-react-stats-<?php echo $comment['id']; ?>"
                     style="cursor: pointer;" onclick="showReactions(<?php echo $comment['id']; ?>, 'comment')">
                </div>
                <?php endif; ?>
            </div>

            <!-- Reply Input (Hidden) -->
            <div id="reply-input-<?php echo $comment['id']; ?>" class="mt-2 d-none">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control bg-dark border-secondary text-white rounded-pill px-3" placeholder="เขียนตอบกลับ..." id="reply-field-<?php echo $comment['id']; ?>">
                    <button class="btn btn-primary rounded-pill ms-2 px-3" onclick="submitReply(<?php echo $comment['id']; ?>, <?php echo $comment['post_id']; ?>)">ส่ง</button>
                </div>
            </div>

            <!-- Replies -->
            <?php
            $stmt = $conn->prepare("
                SELECT c.*, u.username, u.first_name, u.last_name, u.role,
                       cp.display_name as community_name, cp.community_username, cp.avatar as community_avatar,
                       m.profile_image as m_img, e.profile_image as e_img, m.band_type, m.band_members
                FROM comments c
                JOIN users u ON c.user_id = u.id
                LEFT JOIN community_profiles cp ON u.id = cp.user_id
                LEFT JOIN musician_profiles m ON u.id = m.user_id
                LEFT JOIN employer_profiles e ON u.id = e.user_id
                WHERE c.parent_id = ?
                ORDER BY c.created_at ASC
            ");
            $stmt->execute([$comment['id']]);
            $replies = $stmt->fetchAll();
            foreach ($replies as $reply) {
                renderComment($conn, $reply, true);
            }
            ?>
        </div>
    </div>
    <?php
}

if ($action === 'post') {
    $content = $_POST['content'] ?? '';
    $type = $_POST['type'] ?? 'text';
    $feeling = $_POST['feeling'] ?? null;
    $location = $_POST['location'] ?? null;
    $media_url = null;
    $poll_question = $_POST['poll_question'] ?? null;
    $poll_options = null;

    if ($type === 'poll') {
        $options = $_POST['poll_options'] ?? [];
        $poll_options = json_encode(array_filter($options), JSON_UNESCAPED_UNICODE);
    }

    if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
        $media_url = uniqid() . '.' . $ext;
        if (!is_dir('uploads/community')) mkdir('uploads/community', 0777, true);
        move_uploaded_file($_FILES['media_file']['tmp_name'], 'uploads/community/' . $media_url);
    }

    $stmt = $conn->prepare("INSERT INTO posts (user_id, content, type, media_url, feeling, location, poll_question, poll_options) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $content, $type, $media_url, $feeling, $location, $poll_question, $poll_options]);
    header("Location: community.php");
    exit();
}

if ($action === 'create_event') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $event_date = $_POST['event_date'];
    $location = $_POST['location'];
    $max_participants = (int)$_POST['max_participants'];

    if (!empty($title) && !empty($event_date)) {
        $stmt = $conn->prepare("INSERT INTO community_events (user_id, title, description, event_date, location, max_participants) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $description, $event_date, $location, $max_participants]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Missing fields']);
    }
}

if ($action === 'join_event') {
    $event_id = $_POST['event_id'];
    try {
        $stmt = $conn->prepare("INSERT INTO event_participants (event_id, user_id) VALUES (?, ?)");
        $stmt->execute([$event_id, $user_id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Already joined or error']);
    }
}

if ($action === 'vote_poll') {
    $post_id = $_POST['post_id'];
    $option_index = (int)$_POST['option_index'];

    // Check if already voted
    $stmt = $conn->prepare("SELECT id FROM poll_votes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$post_id, $user_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $conn->prepare("UPDATE poll_votes SET option_index = ? WHERE id = ?");
        $stmt->execute([$option_index, $existing['id']]);
    } else {
        $stmt = $conn->prepare("INSERT INTO poll_votes (post_id, user_id, option_index) VALUES (?, ?, ?)");
        $stmt->execute([$post_id, $user_id, $option_index]);
    }

    // Get updated results
    $stmt = $conn->prepare("SELECT option_index, COUNT(*) as count FROM poll_votes WHERE post_id = ? GROUP BY option_index");
    $stmt->execute([$post_id]);
    $votes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM poll_votes WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $total_votes = $stmt->fetchColumn();

    echo json_encode([
        'success' => true, 
        'votes' => $votes, 
        'total' => $total_votes,
        'user_choice' => $option_index
    ]);
}

if ($action === 'get_reactions_list') {
    $target_id = $_GET['id'];
    $target_type = $_GET['type']; // 'post' or 'comment'
    $table = ($target_type === 'post') ? 'post_reactions' : 'comment_reactions';
    $column = ($target_type === 'post') ? 'post_id' : 'comment_id';

    $stmt = $conn->prepare("
        SELECT r.reaction_type, u.username, 
               cp.display_name as community_name, cp.avatar as community_avatar,
               m.profile_image as m_img, e.profile_image as e_img, u.role
        FROM $table r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN community_profiles cp ON u.id = cp.user_id
        LEFT JOIN musician_profiles m ON u.id = m.user_id
        LEFT JOIN employer_profiles e ON u.id = e.user_id
        WHERE r.$column = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$target_id]);
    $list = $stmt->fetchAll();

    foreach ($list as $r) {
        $name = !empty($r['community_name']) ? $r['community_name'] : $r['username'];
        $avatar = !empty($r['community_avatar']) ? $r['community_avatar'] : ($r['role'] === 'musician' ? $r['m_img'] : $r['e_img']);
        $avatar_src = !empty($avatar) && $avatar !== 'default_avatar.png' ? 'uploads/avatars/' . $avatar : 'https://ui-avatars.com/api/?name=' . urlencode($r['username']);
        ?>
        <div class="d-flex align-items-center mb-3">
            <div class="position-relative">
                <img src="<?php echo htmlspecialchars($avatar_src); ?>" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                <span class="position-absolute bottom-0 end-0" style="font-size: 0.8rem;"><?php echo getReactionIcon($r['reaction_type']); ?></span>
            </div>
            <div class="ms-3">
                <div class="text-white fw-bold small"><?php echo htmlspecialchars($name); ?></div>
                <div class="text-secondary" style="font-size: 0.7rem;">@<?php echo htmlspecialchars($r['username']); ?></div>
            </div>
        </div>
        <?php
    }
    if (empty($list)) echo '<div class="text-center text-muted py-4">ยังไม่มีการแสดงความรู้สึก</div>';
}
?>
