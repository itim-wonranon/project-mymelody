<?php
require_once 'includes/db.php';
session_start();

$musician_id = $_GET['id'] ?? $_GET['musician_id'] ?? null;

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

// Check if the current user can write a review for this musician
$can_review = false;
$pending_booking_id = null;
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'employer') {
    $can_review = true;
    
    // Find a completed booking with this musician that does NOT have a review yet (if any)
    $stmtReviewCheck = $conn->prepare("
        SELECT b.id 
        FROM bookings b
        LEFT JOIN reviews r ON b.id = r.booking_id
        WHERE b.employer_id = ? AND b.musician_id = ? AND b.status = 'completed' AND r.id IS NULL
        LIMIT 1
    ");
    $stmtReviewCheck->execute([$_SESSION['user_id'], $musician_id]);
    $booking_to_review = $stmtReviewCheck->fetch();
    if ($booking_to_review) {
        $pending_booking_id = $booking_to_review['id'];
    }
}

// Handle Direct Profile Review Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_direct_review']) && isset($_SESSION['user_id']) && $_SESSION['role'] === 'employer') {
    $booking_id = !empty($_POST['booking_id']) ? (int)$_POST['booking_id'] : ($pending_booking_id ?? null);
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);
    
    $valid_booking_id = null;
    if ($booking_id) {
        // Double check that this booking belongs to the employer, musician, and is completed
        $stmtCheckVal = $conn->prepare("
            SELECT id FROM bookings 
            WHERE id = ? AND employer_id = ? AND musician_id = ? AND status = 'completed'
        ");
        $stmtCheckVal->execute([$booking_id, $_SESSION['user_id'], $musician_id]);
        if ($stmtCheckVal->fetch()) {
            $valid_booking_id = $booking_id;
        }
    }
    
    // Insert review into database (booking_id is optional and can be NULL)
    $stmt_ins = $conn->prepare("INSERT INTO reviews (booking_id, employer_id, musician_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
    if ($stmt_ins->execute([$valid_booking_id, $_SESSION['user_id'], $musician_id, $rating, $comment])) {
        // Update musician average rating
        $stmtAvg = $conn->prepare("SELECT AVG(rating) as avg_rating FROM reviews WHERE musician_id = ?");
        $stmtAvg->execute([$musician_id]);
        $avg = $stmtAvg->fetch()['avg_rating'];
        
        $stmtUpdateScore = $conn->prepare("UPDATE musician_profiles SET rating_score = ? WHERE user_id = ?");
        $stmtUpdateScore->execute([$avg, $musician_id]);
        
        $review_success = "ขอบคุณสำหรับการส่งคะแนนและรีวิวศิลปินเรียบร้อยแล้ว!";
            
            // Re-fetch reviews to display the newly added review immediately
            $stmt = $conn->prepare("
                SELECT r.rating, r.comment, r.created_at, u.username as employer_name 
                FROM reviews r 
                JOIN users u ON r.employer_id = u.id 
                WHERE r.musician_id = ? 
                ORDER BY r.created_at DESC LIMIT 5
            ");
            $stmt->execute([$musician_id]);
            $reviews = $stmt->fetchAll();
            
            // Re-fetch musician details to show updated rating score in header
            $stmtMus = $conn->prepare("
                SELECT u.username, u.first_name, u.last_name, m.* 
                FROM users u 
                JOIN musician_profiles m ON u.id = m.user_id 
                WHERE u.id = ? AND u.role = 'musician'
            ");
            $stmtMus->execute([$musician_id]);
            $musician = $stmtMus->fetch();
            
            // Keep can_review status true for employers so they can submit ratings continuously
            $can_review = (isset($_SESSION['user_id']) && $_SESSION['role'] === 'employer');
        }
}

// Parse JSONs
$band_data = json_decode($musician['band_members'], true);
$work_areas = json_decode($musician['work_areas'], true) ?? [];
$availability_days = json_decode($musician['availability_days'], true) ?? [];
$availability_times = json_decode($musician['availability_times'], true) ?? [];
$location_str = empty($work_areas) ? 'ไม่ได้ระบุพื้นที่' : implode(', ', $work_areas);

// PHP helper to extract YouTube ID
if (!function_exists('get_youtube_video_id')) {
    function get_youtube_video_id($url) {
        preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|[^/]+[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
        return isset($match[1]) ? $match[1] : null;
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="container py-5 animate__animated animate__fadeIn">
    <?php 
    $img_src = !empty($musician['profile_image']) && $musician['profile_image'] !== 'default_avatar.png' ? 'uploads/avatars/' . $musician['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($musician['username']).'&background=0D8ABC&color=fff';
    
    // Choose cover background image (either first portfolio image or dynamic dark gradient blur cover)
    $cover_bg = 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?auto=format&fit=crop&w=1200&q=80'; // fallback concert photo
    foreach ($portfolios as $p) {
        if ($p['type'] === 'image') {
            $cover_bg = 'uploads/portfolios/' . $p['link'];
            break;
        }
    }
    
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

    <!-- 1. Ambient Glowing Neon Cyber-Deck Hero Banner -->
    <div class="position-relative overflow-hidden rounded-5 mb-5 shadow-lg animate__animated animate__fadeIn" style="height: 380px; background: radial-gradient(circle at 10% 20%, rgba(196, 113, 237, 0.15) 0%, rgba(18, 18, 26, 0) 60%), radial-gradient(circle at 90% 80%, rgba(0, 240, 255, 0.1) 0%, rgba(18, 18, 26, 0) 60%), #0d0d12; border: 1px solid rgba(255, 255, 255, 0.06); box-shadow: 0 15px 45px rgba(0, 0, 0, 0.45) !important;">
        <!-- Futuristic Laser Grid Line Decoration -->
        <div class="position-absolute w-100 h-100 opacity-10" style="background-image: linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px); background-size: 20px 20px;"></div>
        
        <!-- Hero Details Panel -->
        <div class="position-absolute bottom-0 start-0 w-100 p-4 p-md-5 d-flex flex-column flex-md-row align-items-center align-items-md-end gap-4" style="z-index: 2;">
            <!-- Floating Glow Avatar -->
            <div class="premium-avatar-wrapper shadow-lg" style="width: 140px; height: 140px; border-radius: 50%; border: 4px solid var(--accent-color); padding: 4px; background: #0b0b0f; box-shadow: 0 0 25px rgba(255, 142, 251, 0.4) !important;">
                <img src="<?php echo htmlspecialchars($img_src); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
            </div>
            
            <!-- Artist Credentials -->
            <div class="flex-grow-1 text-center text-md-start">
                <!-- Verified Badge Capsule -->
                <span class="badge px-3 py-2 rounded-pill mb-3" style="background: rgba(255, 142, 251, 0.12); border: 1px solid rgba(255, 142, 251, 0.35); color: #ff8efb; font-size: 0.75rem; text-shadow: 0 0 10px rgba(255, 142, 251, 0.4);">
                    <i class="fas fa-check-circle animate__pulse me-2"></i> VERIFIED MY MELODY ARTIST
                </span>
                
                <!-- Artist Name with glowing text gradient -->
                <h1 class="fw-extrabold text-white mb-3" style="font-size: 3rem; background: linear-gradient(135deg, #ffffff 40%, #ff8efb 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 0 35px rgba(255, 142, 251, 0.25); font-family: 'Outfit', sans-serif; letter-spacing: -0.5px;">
                    <?php echo htmlspecialchars($display_name); ?>
                </h1>
                
                <!-- Sub-details: genres, rating, location re-arranged into premium capsule badges with increased spacing -->
                <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-start gap-3 mt-2">
                    <div class="d-flex align-items-center px-3 py-1.5 rounded-pill text-light" style="background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08); font-size: 0.8rem;">
                        <i class="fas fa-music text-pink me-2"></i>
                        <span><?php echo htmlspecialchars($musician['genres']); ?></span>
                    </div>
                    
                    <div class="d-flex align-items-center px-3 py-1.5 rounded-pill" style="background: rgba(241, 196, 15, 0.08); border: 1px solid rgba(241, 196, 15, 0.2); color: #f1c40f; font-size: 0.8rem; font-weight: bold; filter: drop-shadow(0 0 4px rgba(241, 196, 15, 0.15));">
                        <i class="fas fa-star text-warning me-2"></i>
                        <span><?php echo number_format($musician['rating_score'], 1); ?> คะแนนรีวิว</span>
                    </div>
                    
                    <div class="d-flex align-items-center px-3 py-1.5 rounded-pill" style="background: rgba(0, 240, 255, 0.08); border: 1px solid rgba(0, 240, 255, 0.2); color: #00f0ff; font-size: 0.8rem; font-weight: bold; filter: drop-shadow(0 0 4px rgba(0, 240, 255, 0.15));">
                        <i class="fas fa-map-marker-alt text-cyan me-2"></i>
                        <span><?php echo htmlspecialchars(mb_strimwidth($location_str, 0, 45, "...")); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Dynamic Booking Button -->
            <div class="text-end mb-2">
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'employer'): ?>
                    <a href="booking.php?musician_id=<?php echo $musician['user_id']; ?>" class="btn btn-primary rounded-pill px-5 py-3 glow-btn shadow-lg fw-bold animate__animated animate__pulse animate__infinite" style="color: #3b0059; font-size: 1.1rem; animation-duration: 2s; border: none;">
                        <i class="far fa-calendar-check me-2.5"></i>จองคิวงานแสดง
                    </a>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <a href="login.php" class="btn btn-outline-light rounded-pill px-5 py-3 fw-bold" style="border-width: 2px;">
                        <i class="fas fa-sign-in-alt me-2.5"></i>เข้าสู่ระบบเพื่อจองคิว
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- 2. Left Column (Essential Info Card Deck) -->
        <div class="col-lg-4 mb-4">
            <!-- 2a. base info glass card -->
            <div class="card glass-card-premium p-4 mb-4">
                <h5 class="fw-bold text-white mb-3 pb-2 border-bottom border-secondary border-opacity-15">
                    <i class="fas fa-info-circle text-cyan me-3"></i>ข้อมูลพื้นฐานศิลปิน
                </h5>
                <div class="d-flex flex-column gap-3 text-light">
                    <div class="d-flex align-items-center justify-content-between rounded-3" style="padding: 12px 18px; background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.05);">
                        <span class="text-secondary fw-bold" style="font-size: 0.9rem;"><i class="fas fa-users text-cyan me-3"></i>รูปแบบศิลปิน:</span>
                        <span class="fw-bold text-light" style="font-size: 0.9rem;">
                            <?php 
                            if ($musician['band_type'] === 'solo') echo 'ศิลปินเดี่ยว (Solo)';
                            elseif ($musician['band_type'] === 'duo') echo 'ศิลปินคู่ (Duo)';
                            else echo 'วงดนตรี (Band)';
                            ?>
                        </span>
                    </div>
                    
                    <div class="d-flex align-items-center justify-content-between rounded-3" style="padding: 12px 18px; background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.05);">
                        <span class="text-secondary fw-bold" style="font-size: 0.9rem;"><i class="fas fa-dollar-sign text-cyan me-3"></i>เรทค่าจ้างเริ่มต้น:</span>
                        <span class="fw-extrabold text-cyan" style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; text-shadow: 0 0 10px rgba(0, 240, 255, 0.35);">
                            <?php echo number_format(floatval($musician['rate'])); ?> <span class="fs-6 fw-normal text-secondary">บาท/<?php echo $musician['pricing_type'] == 'hour' ? 'ชม.' : 'วัน'; ?></span>
                        </span>
                    </div>

                    <?php if ($musician['band_type'] === 'solo'): ?>
                        <div class="d-flex align-items-center justify-content-between rounded-3" style="padding: 12px 18px; background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.05);">
                            <span class="text-secondary fw-bold" style="font-size: 0.9rem;"><i class="fas fa-user-clock text-cyan me-3"></i>อายุศิลปิน:</span>
                            <span class="fw-bold text-light" style="font-size: 0.9rem;"><?php echo htmlspecialchars($musician['age']); ?> ปี</span>
                        </div>
                        
                        <div class="d-flex align-items-center justify-content-between rounded-3" style="padding: 12px 18px; background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.05);">
                            <span class="text-secondary fw-bold" style="font-size: 0.9rem;"><i class="fas fa-guitar text-cyan me-3"></i>เครื่องดนตรีคู่กาย:</span>
                            <span class="fw-bold text-light text-end" style="font-size: 0.9rem;"><?php echo htmlspecialchars($musician['instruments']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2b. band members card (If applicable) -->
            <?php if ($musician['band_type'] !== 'solo' && !empty($band_data['members'])): ?>
                <div class="card glass-card-premium p-4 mb-4">
                    <h5 class="fw-bold text-white mb-3 pb-2 border-bottom border-secondary border-opacity-15">
                        <i class="fas fa-users-cog text-pink me-3"></i>ทำความรู้จักสมาชิกวง
                    </h5>
                    <div class="d-flex flex-column gap-2.5">
                        <?php foreach ($band_data['members'] as $m): ?>
                            <div class="d-flex justify-content-between align-items-center rounded-3" style="padding: 14px 18px; background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.05);">
                                <div>
                                    <span class="fw-bold text-light d-block mb-1" style="font-size: 0.95rem;"><i class="far fa-user text-cyan me-2.5"></i><?php echo htmlspecialchars($m['name']); ?></span>
                                    <span class="text-secondary d-block" style="font-size: 0.8rem; margin-left: 28px;">อายุ <?php echo htmlspecialchars($m['age']); ?> ปี</span>
                                </div>
                                <span class="badge bg-pink bg-opacity-15 text-pink border border-pink border-opacity-20 rounded-pill px-3 py-1.5 fw-bold" style="font-size: 0.75rem;">
                                    <?php echo htmlspecialchars($m['instrument']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- 3. Right Column (Calendar, Portfolios & Reviews) -->
        <div class="col-lg-8">
            <!-- 3a. Reviews & Direct Artist Rating System -->
            <div class="card glass-card-premium p-4 mb-4">
                <h4 class="fw-bold mb-2 text-white" style="font-size: 1.25rem;">
                    <i class="fas fa-star text-warning me-3 animate__pulse"></i>รีวิวและคะแนนของศิลปิน (Ratings & Reviews)
                </h4>
                <p class="text-secondary small mb-4">ความคิดเห็นและคำชมเชยจากผู้ใช้บริการที่ร่วมงานจริงกับศิลปิน</p>
                
                <?php if (isset($review_success)): ?>
                    <div class="alert alert-success alert-dismissible fade show bg-success bg-opacity-20 border-success border-opacity-30 text-white rounded-3 mb-4" role="alert">
                        <i class="fas fa-check-circle me-2 text-success"></i> <?php echo $review_success; ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($can_review): ?>
                    <div class="p-4 bg-dark bg-opacity-40 border border-warning border-opacity-25 rounded-4 mb-4 animate__animated animate__pulse" style="box-shadow: 0 0 15px rgba(243, 156, 18, 0.15);">
                        <h5 class="fw-bold mb-2 text-warning" style="font-size: 1.1rem;">
                            <i class="fas fa-magic me-2"></i>คุณร่วมงานการแสดงดนตรีเรียบร้อยแล้ว! เขียนรีวิวและให้คะแนนที่นี่
                        </h5>
                        <p class="text-secondary small mb-3">ช่วยแชร์ความประทับใจและความคิดเห็นของคุณเพื่อช่วยโปรโมตศิลปินกันเถอะ</p>
                        
                        <form method="POST" action="musician_profile.php?id=<?php echo $musician_id; ?>">
                            <input type="hidden" name="submit_direct_review" value="1">
                            <input type="hidden" name="booking_id" value="<?php echo $pending_booking_id; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label text-light small fw-bold mb-2">ให้คะแนนความประทับใจ</label>
                                <div class="d-flex align-items-center gap-3 my-2 star-rating-interactive" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); padding: 12px 18px; border-radius: 14px;">
                                    <input type="hidden" name="rating" id="selected_rating_value" value="5" required>
                                    <div class="d-flex gap-2">
                                        <i class="far fa-star text-secondary cursor-pointer fa-2x star-node" data-value="1" style="cursor: pointer; transition: transform 0.2s, color 0.2s;"></i>
                                        <i class="far fa-star text-secondary cursor-pointer fa-2x star-node" data-value="2" style="cursor: pointer; transition: transform 0.2s, color 0.2s;"></i>
                                        <i class="far fa-star text-secondary cursor-pointer fa-2x star-node" data-value="3" style="cursor: pointer; transition: transform 0.2s, color 0.2s;"></i>
                                        <i class="far fa-star text-secondary cursor-pointer fa-2x star-node" data-value="4" style="cursor: pointer; transition: transform 0.2s, color 0.2s;"></i>
                                        <i class="far fa-star text-secondary cursor-pointer fa-2x star-node" data-value="5" style="cursor: pointer; transition: transform 0.2s, color 0.2s;"></i>
                                    </div>
                                    <span class="text-warning small fw-bold ms-2" id="rating_text_feedback" style="text-shadow: 0 0 10px rgba(243, 156, 18, 0.4); min-width: 110px;">(ยอดเยี่ยมที่สุด)</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-light small fw-bold mb-2">เขียนคำวิจารณ์/คำชมเชย</label>
                                <textarea name="comment" class="form-control form-control-premium text-white bg-transparent border-secondary" rows="2" style="border-radius: 12px; padding: 12px;" required placeholder="เขียนรีวิวเพื่อเป็นกำลังใจและคำติชมให้ศิลปิน..."></textarea>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-warning rounded-pill px-4 py-2 fw-bold" style="color: #2c1a04;">
                                    <i class="fas fa-paper-plane me-2"></i>ส่งคะแนนรีวิวศิลปิน
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="p-4 mb-4 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-10 text-center animate__animated animate__fadeIn">
                        <i class="fas fa-star-half-alt text-secondary fa-2x mb-2 opacity-50"></i>
                        <p class="text-secondary small mb-3">ระบบประเมินดวงดาวและรีวิวเปิดให้เฉพาะ <strong>ผู้ว่าจ้าง (Employer)</strong> ที่เข้าสู่ระบบเขียนผลงานร่วมงานจริง</p>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="login.php" class="btn btn-sm btn-cyber-outline btn-cyber-outline-pink rounded-pill px-4 py-2 fw-bold" style="font-size: 0.85rem;">
                                <i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบผู้ว่าจ้างเพื่อเขียนรีวิว
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-column gap-3">
                    <?php if (count($reviews) > 0): ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="p-3 bg-dark bg-opacity-25 border border-secondary border-opacity-15 rounded-4 animate__animated animate__fadeIn">
                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user-circle text-cyan me-2.5 fa-lg"></i>
                                        <strong class="text-light"><?php echo htmlspecialchars($review['employer_name']); ?></strong>
                                        <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-20 ms-2 rounded-pill small" style="font-size: 0.7rem; font-weight: bold;">
                                            <i class="fas fa-check-circle me-1.5"></i> ผู้ว่าจ้างจริง
                                        </span>
                                    </div>
                                    <span class="text-secondary small"><?php echo date('d M Y', strtotime($review['created_at'])); ?></span>
                                </div>
                                <div class="star-rating mb-2" style="font-size: 0.85rem; color: #f1c40f; filter: drop-shadow(0 0 4px rgba(241, 196, 15, 0.4));">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($review['rating'] >= $i) echo '<i class="fas fa-star me-0.5"></i>';
                                        else echo '<i class="far fa-star me-0.5"></i>';
                                    }
                                    ?>
                                </div>
                                <p class="mb-0 text-secondary small" style="line-height: 1.5;"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="far fa-comments fa-3x text-secondary mb-2 opacity-30"></i>
                            <p class="text-secondary small mb-0">ศิลปินท่านนี้ยังไม่ได้รับรีวิวในปัจจุบัน</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 3b. Cyberpunk Neon Availability Calendar -->
            <div class="card glass-card-premium p-4 mb-4">
                <h4 class="fw-bold mb-2 text-white" style="font-size: 1.25rem;"><i class="fas fa-calendar-alt text-pink me-3"></i>ปฏิทินคิวรับงานของศิลปิน</h4>
                <p class="text-secondary small mb-4">คลิกเลือกวันที่ว่างบนปฏิทินเรืองแสงเพื่อตรวจสอบเวลาว่างอย่างละเอียด</p>
                
                <div class="row">
                    <!-- Left: Calendar Widget -->
                    <div class="col-lg-7 mb-4 mb-lg-0">
                        <div class="neon-calendar-container" style="background: rgba(11, 11, 15, 0.35); border: 1px solid rgba(255, 255, 255, 0.05); padding: 16px; border-radius: 16px;">
                            <div class="neon-calendar-header">
                                <button type="button" class="neon-calendar-btn" onclick="prevMonthPublic()"><i class="fas fa-chevron-left"></i></button>
                                <div class="neon-calendar-title text-white fw-bold" id="calendar_title_public">พฤษภาคม 2569</div>
                                <button type="button" class="neon-calendar-btn" onclick="nextMonthPublic()"><i class="fas fa-chevron-right"></i></button>
                            </div>
                            <div class="neon-calendar-weekdays text-secondary">
                                <div>อา</div><div>จ</div><div>อ</div><div>พ</div><div>พฤ</div><div>ศ</div><div>ส</div>
                            </div>
                            <div class="neon-calendar-grid" id="calendar_grid_public">
                                <!-- Days dynamically rendered via Javascript -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right: Info Panel -->
                    <div class="col-lg-5">
                        <div class="neon-time-panel h-100 d-flex flex-column justify-content-center p-4 border border-secondary border-opacity-15 rounded-4 bg-dark bg-opacity-20 text-center">
                            <div id="public_date_empty" class="text-secondary py-5">
                                <i class="fas fa-calendar-check fa-4x mb-3 text-cyan opacity-40"></i>
                                <h5 class="text-light fw-bold">คลิกวันที่ว่างบนปฏิทิน</h5>
                                <p class="small mb-0">เพื่อตรวจสอบคิวรับงานของศิลปินในวันนี้</p>
                            </div>
                            
                            <div id="public_date_info" style="display: none;" class="animate__animated animate__fadeIn">
                                <span class="badge text-white px-3 py-2 rounded-pill fw-bold mb-4" style="background: linear-gradient(135deg, var(--accent-color), var(--primary-color)); font-size: 0.85rem;">
                                    <i class="fas fa-check-circle me-2 animate__pulse"></i> ศิลปินว่างรับงาน
                                </span>
                                <h3 class="text-white fw-bold mb-4" id="public_selected_date_label">วันที่...</h3>
                                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'employer'): ?>
                                    <a href="booking.php?musician_id=<?php echo $musician['user_id']; ?>" id="public_booking_btn" class="btn btn-outline-info rounded-pill px-4 py-2 mt-2">
                                        <i class="fas fa-paper-plane me-2"></i>จองคิวงานในวันนี้
                                    </a>
                                <?php elseif (!isset($_SESSION['user_id'])): ?>
                                    <a href="login.php" class="btn btn-outline-primary rounded-pill px-4 py-2 mt-2">เข้าสู่ระบบเพื่อจองงาน</a>
                                <?php else: ?>
                                    <p class="text-secondary small mt-3 mb-0"><i class="fas fa-info-circle me-1"></i> สิทธิ์การจองคิวงานสงวนไว้สำหรับผู้ว่าจ้างเท่านั้น</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php 
                // Robust hybrid parser supporting both new ISO (YYYY-MM-DD) and old Thai formats concurrently
                $availability_list = [];
                if (is_array($availability_days)) {
                    $thai_months_num = [
                        "มกราคม" => "01", "กุมภาพันธ์" => "02", "มีนาคม" => "03", "เมษายน" => "04",
                        "พฤษภาคม" => "05", "มิถุนายน" => "06", "กรกฎาคม" => "07", "สิงหาคม" => "08",
                        "กันยายน" => "09", "ตุลาคม" => "10", "พฤศจิกายน" => "11", "ธันวาคม" => "12"
                    ];
                    $current_year = date('Y');
                    
                    foreach ($availability_days as $slot) {
                        if (!is_array($slot)) continue;
                        
                        // Type 1: Standard Neon Calendar format {"date": "YYYY-MM-DD", "start": "HH:MM", "end": "HH:MM"}
                        if (isset($slot['date']) && strpos($slot['date'], '-') !== false) {
                            $availability_list[] = [
                                'date' => $slot['date'],
                                'start' => $slot['start'] ?? '18:00',
                                'end' => $slot['end'] ?? '21:00'
                            ];
                        }
                        // Type 2: Legacy Thai calendar format {"month": "พฤษภาคม", "date": "15", "start": "HH:MM", "end": "HH:MM"}
                        elseif (isset($slot['month']) && isset($slot['date'])) {
                            $m_num = $thai_months_num[$slot['month']] ?? '01';
                            $d_num = sprintf('%02d', intval($slot['date']));
                            $availability_list[] = [
                                'date' => "{$current_year}-{$m_num}-{$d_num}",
                                'start' => $slot['start'] ?? '18:00',
                                'end' => $slot['end'] ?? '21:00'
                            ];
                        }
                    }
                }
                ?>

                <?php if (!empty($musician['availability_info'])): ?>
                    <div class="mt-4 p-3 rounded-4 bg-dark bg-opacity-30 border border-secondary border-opacity-15">
                        <strong class="text-warning"><i class="fas fa-exclamation-circle me-2"></i> รายละเอียดเงื่อนไขเพิ่มเติม:</strong>
                        <p class="mb-0 text-light mt-1 small" style="line-height: 1.6;"><?php echo nl2br(htmlspecialchars($musician['availability_info'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 3b. Portfolios Glass Card -->
            <div class="card glass-card-premium p-4 mb-4">
                <h4 class="fw-bold mb-2 text-white" style="font-size: 1.25rem;">
                    <i class="fas fa-photo-video text-pink me-3 animate__pulse"></i>คลังผลงานของศิลปิน (Portfolios)
                </h4>
                <p class="text-secondary small mb-4">รับชมบันทึกรูปภาพ วิดีโอ หรือสตรีมการแสดงเพื่อพิจารณาจ้างงาน</p>
                
                <?php if (count($portfolios) > 0): ?>
                    <div class="row">
                        <?php foreach ($portfolios as $item): ?>
                            <div class="col-md-6 mb-4">
                                <div class="portfolio-card-premium h-100" style="background: rgba(11, 11, 15, 0.3); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column;">
                                    <!-- Media Top Section -->
                                    <?php if ($item['type'] === 'image'): ?>
                                        <span class="portfolio-badge portfolio-badge-image">
                                            <i class="fas fa-image me-2"></i>รูปภาพ
                                        </span>
                                        <div class="portfolio-media-wrapper" style="height: 180px; overflow: hidden; background: #000;">
                                            <img src="uploads/portfolios/<?php echo htmlspecialchars($item['link']); ?>" alt="Portfolio Image" style="width: 100%; height: 100%; object-fit: cover;">
                                        </div>
                                    <?php elseif ($item['type'] === 'video'): ?>
                                        <span class="portfolio-badge portfolio-badge-video">
                                            <i class="fas fa-video me-2"></i>วิดีโอ
                                        </span>
                                        <div class="portfolio-media-wrapper" style="height: 180px; overflow: hidden; background: #000;">
                                            <video src="uploads/portfolios/<?php echo htmlspecialchars($item['link']); ?>" controls style="width: 100%; height: 100%; object-fit: cover;"></video>
                                        </div>
                                    <?php elseif ($item['type'] === 'link'): ?>
                                        <span class="portfolio-badge portfolio-badge-link">
                                            <i class="fas fa-link me-2"></i>ลิงก์ผลงาน
                                        </span>
                                        
                                        <?php 
                                        $yt_id = get_youtube_video_id($item['link']);
                                        if ($yt_id): 
                                            $thumb_url = "https://img.youtube.com/vi/{$yt_id}/hqdefault.jpg";
                                        ?>
                                            <a href="<?php echo htmlspecialchars($item['link']); ?>" target="_blank" class="portfolio-media-wrapper d-block position-relative" style="height: 180px; overflow: hidden;">
                                                <div class="youtube-play-btn"><i class="fas fa-play"></i></div>
                                                <img src="<?php echo $thumb_url; ?>" alt="YouTube Cover" style="width: 100%; height: 100%; object-fit: cover;">
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo htmlspecialchars($item['link']); ?>" target="_blank" class="portfolio-media-wrapper d-block" style="height: 180px; overflow: hidden; background: rgba(0, 0, 0, 0.4);">
                                                <div class="link-placeholder-box d-flex flex-column align-items-center justify-content-center h-100 text-center p-3">
                                                    <i class="fas fa-external-link-alt fa-3x mb-3 text-cyan opacity-40"></i>
                                                    <h6 class="text-light fw-bold mb-0">คลิกชมผลงานภายนอก</h6>
                                                    <p class="small text-secondary mb-0 mt-1"><?php echo parse_url($item['link'], PHP_URL_HOST); ?></p>
                                                </div>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <!-- Card Info Body -->
                                    <div class="card-body p-4 d-flex flex-column justify-content-between flex-grow-1">
                                        <div>
                                            <p class="card-text text-light small mb-3" style="line-height: 1.5; font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($item['description'] ?: 'ไม่มีคำอธิบายผลงาน'); ?>
                                            </p>
                                        </div>
                                        
                                        <?php if ($item['type'] === 'link'): ?>
                                            <div>
                                                <a href="<?php echo htmlspecialchars($item['link']); ?>" target="_blank" class="btn btn-sm btn-cyber-outline btn-cyber-outline-cyan w-100 rounded-pill py-2 fw-bold text-center small">
                                                    เปิดดูคลิปวีดีโอต้นฉบับ <i class="fas fa-external-link-alt ms-1"></i>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-3x text-secondary mb-3 opacity-30"></i>
                        <p class="text-secondary small mb-0">ศิลปินยังไม่ได้เพิ่มข้อมูลคลังผลงานในพอร์ต</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// PUBLIC NEON CALENDAR STATE ENGINE
let publicCalendarData = <?php echo json_encode($availability_list, JSON_UNESCAPED_UNICODE); ?>;
let pubYear, pubMonth;
let pubSelectedDateStr = null;

const pubMonthNamesTH = [
    "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
    "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
];

function renderPublicCalendar() {
    const grid = document.getElementById('calendar_grid_public');
    if (!grid) return;
    grid.innerHTML = '';

    // Month Year Header Title (Buddhist Era +543)
    const titleText = `${pubMonthNamesTH[pubMonth]} ${pubYear + 543}`;
    document.getElementById('calendar_title_public').innerText = titleText;

    const firstDayIndex = new Date(pubYear, pubMonth, 1).getDay();
    const totalDays = new Date(pubYear, pubMonth + 1, 0).getDate();

    // Pin the navigation within the current year only
    const prevBtn = document.querySelector('button[onclick="prevMonthPublic()"]');
    const nextBtn = document.querySelector('button[onclick="nextMonthPublic()"]');
    if (prevBtn) {
        prevBtn.style.opacity = (pubMonth <= 0) ? '0.3' : '1';
        prevBtn.style.pointerEvents = (pubMonth <= 0) ? 'none' : 'auto';
    }
    if (nextBtn) {
        nextBtn.style.opacity = (pubMonth >= 11) ? '0.3' : '1';
        nextBtn.style.pointerEvents = (pubMonth >= 11) ? 'none' : 'auto';
    }

    // Pad blank days of previous month
    for (let i = 0; i < firstDayIndex; i++) {
        const inactiveDiv = document.createElement('div');
        inactiveDiv.className = 'neon-calendar-day inactive';
        grid.appendChild(inactiveDiv);
    }

    // Render days
    for (let day = 1; day <= totalDays; day++) {
        const dayDiv = document.createElement('div');
        dayDiv.className = 'neon-calendar-day';
        dayDiv.innerText = day;

        const dateStr = `${pubYear}-${String(pubMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        dayDiv.setAttribute('data-date', dateStr);

        // Highlight available slots
        const slot = publicCalendarData.find(item => item.date === dateStr);
        if (slot) {
            dayDiv.classList.add('active-slot');
        }

        if (pubSelectedDateStr === dateStr) {
            dayDiv.classList.add('selected');
        }

        dayDiv.addEventListener('click', () => selectPublicDate(dateStr, dayDiv));
        grid.appendChild(dayDiv);
    }
}

function prevMonthPublic() {
    if (pubMonth <= 0) return;
    pubMonth--;
    renderPublicCalendar();
}

function nextMonthPublic() {
    if (pubMonth >= 11) return;
    pubMonth++;
    renderPublicCalendar();
}

function selectPublicDate(dateStr, dayElement) {
    pubSelectedDateStr = dateStr;

    // Toggle selected styling
    document.querySelectorAll('#calendar_grid_public .neon-calendar-day').forEach(el => el.classList.remove('selected'));
    dayElement.classList.add('selected');

    const slot = publicCalendarData.find(item => item.date === dateStr);
    const emptyPanel = document.getElementById('public_date_empty');
    const infoPanel = document.getElementById('public_date_info');

    if (slot) {
        emptyPanel.style.display = 'none';
        infoPanel.style.display = 'block';

        const parts = dateStr.split('-');
        const y = parseInt(parts[0]) + 543;
        const m = pubMonthNamesTH[parseInt(parts[1]) - 1];
        const d = parseInt(parts[2]);
        document.getElementById('public_selected_date_label').innerText = `${d} ${m} ${y}`;
        
        // Prefill booking input if present
        const bookingBtn = document.getElementById('public_booking_btn');
        if (bookingBtn) {
            bookingBtn.href = `booking.php?musician_id=<?php echo $musician['user_id']; ?>&date=${dateStr}`;
        }
    } else {
        emptyPanel.style.display = 'block';
        infoPanel.style.display = 'none';
    }
}

// Initialise Public Calendar & Star Ratings
document.addEventListener('DOMContentLoaded', () => {
    const today = new Date();
    pubYear = today.getFullYear();
    pubMonth = today.getMonth();
    renderPublicCalendar();

    // INTERACTIVE STAR RATING ENGINE
    const starNodes = document.querySelectorAll('.star-rating-interactive .star-node');
    const ratingInput = document.getElementById('selected_rating_value');
    const feedbackText = document.getElementById('rating_text_feedback');
    
    if (starNodes.length > 0 && ratingInput) {
        const textFeedbacks = {
            1: "(ควรปรับปรุง)",
            2: "(พอใช้ได้)",
            3: "(ดีปานกลาง)",
            4: "(ดีเยี่ยม)",
            5: "(ยอดเยี่ยมที่สุด)"
        };
        
        function updateStars(val) {
            starNodes.forEach(node => {
                const starVal = parseInt(node.getAttribute('data-value'));
                if (starVal <= val) {
                    node.className = 'fas fa-star text-warning star-node';
                    node.style.color = '#ffc107';
                    node.style.filter = 'drop-shadow(0 0 6px rgba(255, 193, 7, 0.6))';
                } else {
                    node.className = 'far fa-star text-secondary star-node';
                    node.style.color = '#6c757d';
                    node.style.filter = 'none';
                }
            });
            if (feedbackText && textFeedbacks[val]) {
                feedbackText.innerText = textFeedbacks[val];
            }
        }
        
        // Initial setup
        const initialVal = parseInt(ratingInput.value) || 5;
        updateStars(initialVal);
        
        starNodes.forEach(star => {
            star.addEventListener('mouseover', () => {
                const hoverVal = parseInt(star.getAttribute('data-value'));
                updateStars(hoverVal);
            });
            
            star.addEventListener('mouseout', () => {
                const currentVal = parseInt(ratingInput.value) || 5;
                updateStars(currentVal);
            });
            
            star.addEventListener('click', () => {
                const clickVal = parseInt(star.getAttribute('data-value'));
                ratingInput.value = clickVal;
                updateStars(clickVal);
                
                // Pop animation on click
                star.style.transform = 'scale(1.25)';
                setTimeout(() => {
                    star.style.transform = 'scale(1)';
                }, 150);
            });
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
