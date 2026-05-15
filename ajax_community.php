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

if ($action === 'react') {
    $post_id = $_POST['post_id'];
    $type = $_POST['type'];

    // Check if already reacted
    $stmt = $conn->prepare("SELECT id FROM post_reactions WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$user_id, $post_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $conn->prepare("UPDATE post_reactions SET reaction_type = ? WHERE id = ?");
        $stmt->execute([$type, $existing['id']]);
    } else {
        $stmt = $conn->prepare("INSERT INTO post_reactions (user_id, post_id, reaction_type) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $post_id, $type]);
    }

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM post_reactions WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $count = $stmt->fetch();
    echo json_encode(['success' => true, 'count' => $count['total']]);
}

if ($action === 'comment') {
    $post_id = $_POST['post_id'];
    $content = trim($_POST['content']);

    if (!empty($content)) {
        $stmt = $conn->prepare("INSERT INTO comments (user_id, post_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $post_id, $content]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Empty content']);
    }
}

if ($action === 'get_comments') {
    $post_id = $_GET['post_id'];
    $stmt = $conn->prepare("
        SELECT c.*, u.username, u.first_name, u.last_name, u.role,
               cp.display_name as community_name, cp.community_username, cp.avatar as community_avatar,
               m.profile_image as m_img, e.profile_image as e_img, m.band_type, m.band_members
        FROM comments c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN community_profiles cp ON u.id = cp.user_id
        LEFT JOIN musician_profiles m ON u.id = m.user_id
        LEFT JOIN employer_profiles e ON u.id = e.user_id
        WHERE c.post_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$post_id]);
    $comments = $stmt->fetchAll();

    foreach ($comments as $comment) {
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

        ?>
        <div class="comment-item d-flex gap-2 mb-2">
            <img src="<?php echo htmlspecialchars($avatar_src); ?>" class="post-input-avatar" style="width: 30px; height: 30px;">
            <div class="comment-bubble flex-grow-1">
                <div class="d-flex justify-content-between">
                    <strong class="text-white small"><?php echo htmlspecialchars($name); ?> <span class="text-secondary fw-normal">@<?php echo htmlspecialchars($user_u); ?></span></strong>
                    <small class="text-secondary" style="font-size: 0.6rem;"><?php echo date('H:i, d/m/y', strtotime($comment['created_at'])); ?></small>
                </div>
                <div class="text-light small"><?php echo htmlspecialchars($comment['content']); ?></div>
            </div>
        </div>
        <?php
    }
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
?>
