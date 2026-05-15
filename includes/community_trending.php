<?php
// includes/community_trending.php
require_once 'db.php';

// Fetch trending hashtags from post content
// Simple logic: extract words starting with #
$stmt = $conn->prepare("SELECT content FROM posts WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stmt->execute();
$posts = $stmt->fetchAll();

$hashtags = [];
foreach ($posts as $post) {
    preg_match_all('/#([^\s#]+)/u', $post['content'], $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $tag) {
            $hashtags[$tag] = ($hashtags[$tag] ?? 0) + 1;
        }
    }
}

arsort($hashtags);
$trending_tags = array_slice($hashtags, 0, 5, true);

if (empty($trending_tags)) {
    echo '<div class="text-secondary small py-2">ยังไม่มีเทรนด์ในขณะนี้</div>';
} else {
    foreach ($trending_tags as $tag => $count) {
        echo '<div class="trending-item mb-3">';
        echo '  <a href="community.php?search=' . urlencode('#' . $tag) . '" class="text-decoration-none">';
        echo '    <div class="trending-tag text-white fw-bold">#' . htmlspecialchars($tag) . '</div>';
        echo '    <div class="text-secondary" style="font-size: 0.75rem;">' . $count . ' โพสต์</div>';
        echo '  </a>';
        echo '</div>';
    }
}
?>
