<?php
// includes/reaction_summary.php
// Reusable component to render reaction icons and text

if (!function_exists('getIcon')) {
    function getIcon($type) {
        switch ($type) {
            case 'heart': return '❤️';
            case 'haha': return '😆';
            case 'wow': return '😮';
            case 'sad': return '😢';
            case 'angry': return '😡';
            default: return '👍';
        }
    }
}

if (!function_exists('getBtnText')) {
    function getBtnText($type) {
        switch ($type) {
            case 'heart': return 'รักเลย';
            case 'haha': return 'ฮ่าๆ';
            case 'wow': return 'ว้าว';
            case 'sad': return 'เศร้า';
            case 'angry': return 'โกรธ';
            case 'like': return 'ถูกใจ';
            default: return 'ถูกใจ';
        }
    }
}

function renderReactionSummary($conn, $id, $table, $id_class) {
    $column = ($table === 'post_reactions') ? 'post_id' : 'comment_id';
    
    // Total count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM $table WHERE $column = ?");
    $stmt->execute([$id]);
    $total = $stmt->fetch()['total'];

    // Top 3 reaction types
    $stmt = $conn->prepare("SELECT reaction_type, COUNT(*) as c FROM $table WHERE $column = ? GROUP BY reaction_type ORDER BY c DESC LIMIT 3");
    $stmt->execute([$id]);
    $top = $stmt->fetchAll();

    if ($total > 0) {
        $type = ($table === 'post_reactions') ? 'post' : 'comment';
        echo '<div class="' . $id_class . ' small text-secondary d-flex align-items-center gap-1" style="cursor: pointer;" onclick="showReactions(' . $id . ', \'' . $type . '\')">';
        echo '<div class="reaction-icons-group">';
        foreach ($top as $t) {
            echo '<span>' . getIcon($t['reaction_type']) . '</span>';
        }
        echo '</div>';
        echo '<span class="ms-1">' . $total . ' คน</span>';
        echo '</div>';
    } else {
        echo '<div class="' . $id_class . ' small text-secondary d-none"></div>';
    }
}
?>
