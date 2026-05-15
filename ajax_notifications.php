<?php
// ajax_notifications.php
require_once 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$last_notif_id = $_GET['last_notif_id'] ?? 0;
$last_post_id = $_GET['last_post_id'] ?? 0;

$response = [
    'new_notifications' => [],
    'new_posts_count' => 0,
    'unread_count' => 0
];

// 1. Check for new notifications for toasts
$stmt = $conn->prepare("
    SELECT n.*, u.username, cp.display_name as sender_name, cp.avatar as sender_avatar
    FROM community_notifications n
    JOIN users u ON n.sender_id = u.id
    LEFT JOIN community_profiles cp ON u.id = cp.user_id
    WHERE n.user_id = ? AND n.id > ?
    ORDER BY n.id ASC
");
$stmt->execute([$user_id, $last_notif_id]);
$response['new_notifications'] = $stmt->fetchAll();

// 2. Total unread count for the sidebar badge
$stmt = $conn->prepare("SELECT COUNT(*) FROM community_notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$response['unread_count'] = $stmt->fetchColumn();

// 3. Check for any new posts in the community for the "New Post" indicator
$stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE id > ?");
$stmt->execute([$last_post_id]);
$response['new_posts_count'] = $stmt->fetchColumn();

echo json_encode($response);
?>
