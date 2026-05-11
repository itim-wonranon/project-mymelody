<?php
require_once 'includes/db.php';
session_start();

$query_string = $_GET['q'] ?? '';
$band_type = $_GET['band_type'] ?? '';
$price_max = $_GET['price_max'] ?? '';
$rating_min = $_GET['rating_min'] ?? '';

$sql = "
    SELECT u.id as user_id, u.username, m.profile_image, m.band_type, m.genres, m.rate, m.location, m.rating_score
    FROM users u
    JOIN musician_profiles m ON u.id = m.user_id
    WHERE u.role = 'musician' AND m.is_verified = 1
";

$params = [];

if (!empty($query_string)) {
    $sql .= " AND (u.username LIKE ? OR m.genres LIKE ? OR m.location LIKE ?)";
    $like_q = "%$query_string%";
    $params[] = $like_q;
    $params[] = $like_q;
    $params[] = $like_q;
}

if (!empty($band_type)) {
    $sql .= " AND m.band_type = ?";
    $params[] = $band_type;
}

if (!empty($rating_min)) {
    $sql .= " AND m.rating_score >= ?";
    $params[] = $rating_min;
}

$sql .= " ORDER BY m.rating_score DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();
?>
<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <h2 class="fw-bold mb-4">ค้นหานักดนตรี</h2>
    
    <div class="row">
        <!-- Filters Sidebar -->
        <div class="col-md-3 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-filter me-2 text-primary"></i>ตัวกรอง</h5>
                </div>
                <div class="card-body">
                    <form action="search.php" method="GET">
                        <div class="mb-3">
                            <label class="form-label">คำค้นหา</label>
                            <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($query_string); ?>" placeholder="ชื่อ, แนวเพลง, สถานที่">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ประเภท</label>
                            <select name="band_type" class="form-select">
                                <option value="">ทั้งหมด</option>
                                <option value="solo" <?php echo $band_type === 'solo' ? 'selected' : ''; ?>>ศิลปินเดี่ยว</option>
                                <option value="band" <?php echo $band_type === 'band' ? 'selected' : ''; ?>>วงดนตรี</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">คะแนนรีวิวขั้นต่ำ</label>
                            <select name="rating_min" class="form-select">
                                <option value="">ทั้งหมด</option>
                                <option value="4" <?php echo $rating_min == '4' ? 'selected' : ''; ?>>4 ดาวขึ้นไป</option>
                                <option value="3" <?php echo $rating_min == '3' ? 'selected' : ''; ?>>3 ดาวขึ้นไป</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 rounded-pill">ค้นหา</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Search Results -->
        <div class="col-md-9">
            <div class="mb-3">
                พบผลลัพธ์ <strong><?php echo count($results); ?></strong> รายการ
            </div>
            
            <div class="row">
                <?php if (count($results) > 0): ?>
                    <?php foreach ($results as $musician): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <?php 
                                    $img_src = !empty($musician['profile_image']) && $musician['profile_image'] !== 'default_avatar.png' ? 'uploads/avatars/' . $musician['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($musician['username']).'&background=0D8ABC&color=fff';
                                    ?>
                                    <img src="<?php echo htmlspecialchars($img_src); ?>" class="profile-img mb-3" alt="Profile">
                                    <h5 class="card-title fw-bold"><?php echo htmlspecialchars($musician['username']); ?></h5>
                                    
                                    <p class="text-muted small mb-1"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo htmlspecialchars($musician['location'] ?: 'ไม่ระบุ'); ?></p>
                                    <p class="text-muted small mb-2"><i class="fas fa-music text-primary me-1"></i> <?php echo htmlspecialchars($musician['genres']); ?></p>
                                    
                                    <div class="star-rating mb-3">
                                        <?php 
                                        $score = $musician['rating_score'];
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($score >= $i) echo '<i class="fas fa-star"></i>';
                                            else if ($score >= $i - 0.5) echo '<i class="fas fa-star-half-alt"></i>';
                                            else echo '<i class="far fa-star"></i>';
                                        }
                                        ?>
                                        <span class="text-dark ms-1 fw-bold"><?php echo number_format($score, 1); ?></span>
                                    </div>
                                    <a href="musician_profile.php?id=<?php echo $musician['user_id']; ?>" class="btn btn-outline-primary rounded-pill w-100">ดูโปรไฟล์</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">ไม่พบข้อมูลที่ตรงกับเงื่อนไข</h4>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
