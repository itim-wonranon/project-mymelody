<?php
require_once 'includes/db.php';
session_start();

$musician_id = $_GET['id'] ?? null;

if (!$musician_id) {
    header("Location: search.php");
    exit();
}

// Fetch Musician Data
$stmt = $conn->prepare("
    SELECT u.username, u.first_name, u.last_name, m.* 
    FROM users u 
    JOIN musician_profiles m ON u.id = m.user_id 
    WHERE u.id = ? AND u.role = 'musician'
");
$stmt->execute([$musician_id]);
$musician = $stmt->fetch();

if (!$musician) {
    die("ไม่พบข้อมูลนักดนตรี");
}

// Fetch Portfolios
$stmt = $conn->prepare("SELECT * FROM portfolios WHERE musician_id = ? ORDER BY created_at DESC");
$stmt->execute([$musician_id]);
$portfolios = $stmt->fetchAll();

// Fetch Reviews
$stmt = $conn->prepare("
    SELECT r.rating, r.comment, r.created_at, u.username as employer_name 
    FROM reviews r 
    JOIN users u ON r.employer_id = u.id 
    WHERE r.musician_id = ? 
    ORDER BY r.created_at DESC LIMIT 5
");
$stmt->execute([$musician_id]);
$reviews = $stmt->fetchAll();

// Parse JSONs
$band_data = json_decode($musician['band_members'], true);
$work_areas = json_decode($musician['work_areas'], true) ?? [];
$availability_days = json_decode($musician['availability_days'], true) ?? [];
$availability_times = json_decode($musician['availability_times'], true) ?? [];
$location_str = empty($work_areas) ? 'ไม่ได้ระบุพื้นที่' : implode(', ', $work_areas);

?>
<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <div class="row">
        <!-- Sidebar Profile -->
        <div class="col-md-4 mb-4">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <?php 
                    $img_src = !empty($musician['profile_image']) && $musician['profile_image'] !== 'default_avatar.png' ? 'uploads/avatars/' . $musician['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($musician['username']).'&background=0D8ABC&color=fff';
                    ?>
                    <img src="<?php echo htmlspecialchars($img_src); ?>" class="profile-img mb-3" alt="Profile">
                    <?php 
                    $display_name = $musician['username'];
                    if ($musician['band_type'] === 'solo') {
                        if (!empty($musician['first_name'])) {
                            $display_name = $musician['first_name'] . ' ' . $musician['last_name'];
                        }
                    } else {
                        if (!empty($band_data['band_name'])) {
                            $display_name = $band_data['band_name'];
                        }
                    }
                    ?>
                    <h3 class="card-title fw-bold"><?php echo htmlspecialchars($display_name); ?></h3>
                    
                    <div class="star-rating mb-2">
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
                    
                    <p class="text-muted"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo htmlspecialchars(mb_strimwidth($location_str, 0, 30, "...")); ?></p>
                    
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'employer'): ?>
                        <a href="booking.php?musician_id=<?php echo $musician['user_id']; ?>" class="btn btn-primary rounded-pill w-100 mt-2 mb-2">
                            <i class="far fa-calendar-check me-2"></i>จองคิวงาน
                        </a>
                    <?php elseif (!isset($_SESSION['user_id'])): ?>
                        <a href="login.php" class="btn btn-outline-primary rounded-pill w-100 mt-2 mb-2">เข้าสู่ระบบเพื่อจองงาน</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white"><h5 class="mb-0 fw-bold">ข้อมูลพื้นฐาน</h5></div>
                <div class="card-body">
                        <li class="mb-2"><strong>ประเภท:</strong> 
                            <?php 
                            if ($musician['band_type'] === 'solo') echo 'ศิลปินเดี่ยว';
                            elseif ($musician['band_type'] === 'duo') echo 'ศิลปินคู่';
                            else echo 'วงดนตรี';
                            ?>
                        </li>
                        <li class="mb-2"><strong>แนวเพลง:</strong> <?php echo htmlspecialchars($musician['genres']); ?></li>
                        <li class="mb-2"><strong>เรทค่าจ้าง:</strong> <?php echo htmlspecialchars($musician['rate']); ?> บาท/<?php echo $musician['pricing_type'] == 'hour' ? 'ชั่วโมง' : 'วัน'; ?></li>
                        
                        <?php if ($musician['band_type'] === 'solo'): ?>
                            <li class="mb-2"><strong>อายุ:</strong> <?php echo htmlspecialchars($musician['age']); ?> ปี</li>
                            <li class="mb-2"><strong>เครื่องดนตรีที่ถนัด:</strong> <?php echo htmlspecialchars($musician['instruments']); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <?php if ($musician['band_type'] !== 'solo' && !empty($band_data['members'])): ?>
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white"><h5 class="mb-0 fw-bold">สมาชิกวง: <?php echo htmlspecialchars($band_data['band_name'] ?? ''); ?></h5></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($band_data['members'] as $m): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-user-circle text-muted me-2"></i><strong><?php echo htmlspecialchars($m['name']); ?></strong> 
                                <small class="text-muted ms-2">(<?php echo htmlspecialchars($m['age']); ?> ปี)</small>
                            </div>
                            <span class="badge bg-secondary rounded-pill"><?php echo htmlspecialchars($m['instrument']); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><h5 class="mb-0 fw-bold">แนะนำตัว</h5></div>
                <div class="card-body">
                    <p><?php echo nl2br(htmlspecialchars($musician['bio'] ?: 'ยังไม่มีข้อมูลแนะนำตัว')); ?></p>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><h5 class="mb-0 fw-bold">ตารางงานที่ว่าง</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <h6 class="fw-bold text-primary"><i class="fas fa-calendar-day me-2"></i>ตารางวันและเวลารับงาน</h6>
                            <ul class="list-unstyled ps-4">
                                <?php 
                                $days_mapping = [
                                    'Monday' => 'วันจันทร์', 'Tuesday' => 'วันอังคาร', 'Wednesday' => 'วันพุธ', 
                                    'Thursday' => 'วันพฤหัสบดี', 'Friday' => 'วันศุกร์', 'Saturday' => 'วันเสาร์', 'Sunday' => 'วันอาทิตย์'
                                ];
                                if(empty($availability_days)): ?>
                                    <li><i class="fas fa-minus text-muted me-2"></i>ไม่ได้ระบุ</li>
                                <?php else: ?>
                                    <?php 
                                    // Check if it's the old format (array of strings) or new format (associative array)
                                    $is_new_format = false;
                                    foreach($availability_days as $key => $val) {
                                        if(is_array($val)) { $is_new_format = true; break; }
                                    }

                                    if ($is_new_format) {
                                        foreach($availability_days as $en_day => $time): 
                                            $th_day = $days_mapping[$en_day] ?? $en_day;
                                            $time_str = ($time['start'] && $time['end']) ? $time['start'] . ' - ' . $time['end'] . ' น.' : 'ไม่ได้ระบุเวลา';
                                        ?>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i><strong><?php echo $th_day; ?>:</strong> <span class="text-info"><?php echo htmlspecialchars($time_str); ?></span></li>
                                        <?php endforeach; 
                                    } else {
                                        // Old format fallback
                                        foreach($availability_days as $day): ?>
                                            <li><i class="fas fa-check text-success me-2"></i><?php echo htmlspecialchars($day); ?></li>
                                        <?php endforeach;
                                    }
                                    ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    <?php if(!empty($musician['availability_info'])): ?>
                        <div class="mt-3 p-3 bg-light rounded">
                            <strong>หมายเหตุเพิ่มเติม: </strong> <?php echo nl2br(htmlspecialchars($musician['availability_info'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><h5 class="mb-0 fw-bold">ผลงาน</h5></div>
                <div class="card-body">
                    <?php if (count($portfolios) > 0): ?>
                        <div class="row">
                            <?php foreach ($portfolios as $item): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100 border">
                                        <div class="card-body p-0">
                                            <?php if ($item['type'] === 'image'): ?>
                                                <img src="uploads/portfolios/<?php echo htmlspecialchars($item['link']); ?>" class="card-img-top" style="height: 200px; object-fit: cover;" alt="Portfolio">
                                            <?php elseif ($item['type'] === 'video'): ?>
                                                <video src="uploads/portfolios/<?php echo htmlspecialchars($item['link']); ?>" class="card-img-top" style="height: 200px; object-fit: cover; background:#000;" controls></video>
                                            <?php elseif ($item['type'] === 'link'): ?>
                                                <div class="card-img-top d-flex align-items-center justify-content-center bg-secondary" style="height: 200px;">
                                                    <i class="fas fa-link fa-4x text-light opacity-50"></i>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="p-3">
                                                <h6 class="card-title text-truncate mb-2"><?php echo htmlspecialchars($item['description'] ?: 'ไม่มีคำอธิบาย'); ?></h6>
                                                <?php if ($item['type'] === 'link'): ?>
                                                    <a href="<?php echo htmlspecialchars($item['link']); ?>" target="_blank" class="btn btn-sm btn-outline-primary w-100">เปิดลิงก์ <i class="fas fa-external-link-alt ms-1"></i></a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">ยังไม่มีผลงาน</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Reviews -->
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0 fw-bold">รีวิวจากผู้ว่าจ้าง</h5></div>
                <div class="card-body">
                    <?php if (count($reviews) > 0): ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="border-bottom pb-3 mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong class="text-primary"><?php echo htmlspecialchars($review['employer_name']); ?></strong>
                                    <span class="text-muted small"><?php echo date('d M Y', strtotime($review['created_at'])); ?></span>
                                </div>
                                <div class="star-rating mb-2" style="font-size: 0.9rem;">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($review['rating'] >= $i) echo '<i class="fas fa-star"></i>';
                                        else echo '<i class="far fa-star"></i>';
                                    }
                                    ?>
                                </div>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">ยังไม่มีรีวิว</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
