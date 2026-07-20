<?php
require_once 'includes/db.php';
session_start();
$hide_footer = true;

// Get filter parameters
$artist_name = $_GET['artist_name'] ?? '';
$band_type   = $_GET['band_type'] ?? '';
$price_max   = $_GET['price_max'] ?? '';
$location    = $_GET['location'] ?? '';
$rating_min  = $_GET['rating_min'] ?? '';

// Check if a search has been initiated
$has_searched = isset($_GET['artist_name']) || isset($_GET['band_type']) || isset($_GET['price_max']) || isset($_GET['location']) || isset($_GET['rating_min']);

// Build Base SQL
$sql = "
    SELECT u.id as user_id, u.username, u.first_name, u.last_name, 
           m.profile_image, m.band_type, m.band_members, m.genres, 
           m.rate, m.location, m.work_areas, m.rating_score
    FROM users u
    JOIN musician_profiles m ON u.id = m.user_id
    WHERE u.role = 'musician' AND m.is_verified = 1
";

$params = [];

// Apply Filters
if (!empty($artist_name)) {
    $sql .= " AND (u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR m.band_members LIKE ? OR m.genres LIKE ?)";
    $like_name = "%$artist_name%";
    array_push($params, $like_name, $like_name, $like_name, $like_name, $like_name);
}

if (!empty($band_type)) {
    $sql .= " AND m.band_type = ?";
    $params[] = $band_type;
}

if (!empty($price_max) && is_numeric($price_max)) {
    // Cast rate to unsigned int for comparison (handles strings like "1500" or "3000-5000" starting with number)
    $sql .= " AND CAST(m.rate AS UNSIGNED) <= ?";
    $params[] = (int)$price_max;
}

if (!empty($location)) {
    $sql .= " AND (m.location LIKE ? OR m.work_areas LIKE ?)";
    $like_loc = "%$location%";
    array_push($params, $like_loc, $like_loc);
}

if (!empty($rating_min) && is_numeric($rating_min)) {
    $sql .= " AND m.rating_score >= ?";
    $params[] = (float)$rating_min;
}

$sql .= " ORDER BY m.rating_score DESC, u.created_at DESC";

$results = [];
if ($has_searched) {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}
?>
<?php include 'includes/header.php'; ?>

<!-- Custom styling moved to css/style.css -->

<!-- Interactive Background Canvas -->
<canvas id="noteCanvas" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; pointer-events: none; z-index: 0;"></canvas>

<div class="container py-5" style="position: relative; z-index: 1;">
    
    <!-- Hero / Top Search Section -->
    <div class="text-center mb-5">
        <h6 class="text-primary fw-bold text-uppercase mb-2" style="letter-spacing: 2px; font-size: 0.85rem;">Discover Talents</h6>
        <h1 class="fw-bold text-white display-5 mb-4">ค้นหานักดนตรีที่คุณต้องการ</h1>
        <p class="text-secondary mb-4 mx-auto" style="max-width: 600px;">ค้นหาศิลปิน วงดนตรี หรือดีเจที่ตรงกับสไตล์และงบประมาณของคุณ เพื่อทำให้งานของคุณสมบูรณ์แบบที่สุด</p>
        
        <!-- Horizontal Search Form -->
        <div class="card bg-dark bg-opacity-50 border border-secondary border-opacity-25 rounded-4 p-4 shadow-lg mx-auto" style="max-width: 900px; backdrop-filter: blur(10px);">
            <form action="search.php" method="GET" id="searchForm">
                <div class="row g-3 text-start">
                    
                    <!-- Keyword Search -->
                    <div class="col-md-9">
                        <label class="form-label text-light small fw-bold mb-1">คำค้นหา / ชื่อศิลปิน / แนวเพลง</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-muted"><i class="fas fa-search"></i></span>
                            <input type="text" name="artist_name" class="form-control bg-dark text-white border-secondary" value="<?php echo htmlspecialchars($artist_name); ?>" placeholder="ค้นหาชื่อศิลปิน, วงดนตรี, หรือแนวเพลง (เช่น Pop, Rock)...">
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 rounded-3 fw-bold py-2 shadow-glow">
                            <i class="fas fa-search me-2"></i> ค้นหา
                        </button>
                    </div>

                    <!-- Collapsible Advanced Filters -->
                    <div class="col-12 mt-3">
                        <a class="text-decoration-none small text-primary fw-bold" data-bs-toggle="collapse" href="#advancedFilters" role="button" aria-expanded="false" aria-controls="advancedFilters">
                            <i class="fas fa-sliders-h me-1"></i> ตัวกรองเพิ่มเติม
                        </a>
                    </div>
                    
                    <div class="collapse col-12 mt-3" id="advancedFilters">
                        <div class="row g-3 p-3 bg-black bg-opacity-25 rounded-3 border border-secondary border-opacity-10">
                            <!-- Location -->
                            <div class="col-md-4">
                                <label class="form-label text-light small fw-bold mb-1">พื้นที่รับงาน</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary text-muted"><i class="fas fa-map-marker-alt"></i></span>
                                    <input type="text" name="location" class="form-control bg-dark text-white border-secondary" value="<?php echo htmlspecialchars($location); ?>" placeholder="เช่น กรุงเทพ, เชียงใหม่">
                                </div>
                            </div>
                            
                            <!-- Artist Type -->
                            <div class="col-md-3">
                                <label class="form-label text-light small fw-bold mb-1">ประเภทศิลปิน</label>
                                <select name="band_type" class="form-select bg-dark text-white border-secondary">
                                    <option value="">ทั้งหมด</option>
                                    <option value="solo" <?php echo $band_type === 'solo' ? 'selected' : ''; ?>>ศิลปินเดี่ยว (Solo)</option>
                                    <option value="band" <?php echo $band_type === 'band' ? 'selected' : ''; ?>>วงดนตรี (Band)</option>
                                </select>
                            </div>
                            
                            <!-- Rating -->
                            <div class="col-md-2">
                                <label class="form-label text-light small fw-bold mb-1">คะแนนรีวิว</label>
                                <select name="rating_min" class="form-select bg-dark text-white border-secondary">
                                    <option value="">ทั้งหมด</option>
                                    <option value="4.5" <?php echo $rating_min == '4.5' ? 'selected' : ''; ?>>4.5+</option>
                                    <option value="4.0" <?php echo $rating_min == '4.0' ? 'selected' : ''; ?>>4.0+</option>
                                    <option value="3.0" <?php echo $rating_min == '3.0' ? 'selected' : ''; ?>>3.0+</option>
                                </select>
                            </div>
                            
                            <!-- Max Price -->
                            <div class="col-md-3">
                                <label class="form-label text-light small fw-bold mb-1 d-flex justify-content-between">
                                    <span>งบสูงสุด</span>
                                    <span class="text-primary">฿<span id="priceValue"><?php echo !empty($price_max) ? htmlspecialchars($price_max) : '20000'; ?></span></span>
                                </label>
                                <input type="range" name="price_max" class="form-range" min="500" max="50000" step="500" value="<?php echo !empty($price_max) ? htmlspecialchars($price_max) : '20000'; ?>" id="priceSlider">
                            </div>
                        </div>
                    </div>
                    
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($has_searched): ?>
    <!-- Search Results Summary -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary border-opacity-25">
        <h5 class="text-white mb-0">ผลลัพธ์การค้นหา <span class="badge bg-primary rounded-pill ms-2"><?php echo count($results); ?></span></h5>
        <?php if(!empty($artist_name) || !empty($band_type) || !empty($location)): ?>
            <a href="search.php" class="btn btn-sm btn-outline-secondary rounded-pill">ล้างตัวกรองทั้งหมด</a>
        <?php endif; ?>
    </div>
    
    <!-- Results Grid -->
    <?php if (count($results) > 0): ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
            <?php foreach ($results as $musician): ?>
                <div class="col">
                    <div class="artist-card h-100 d-flex flex-column rounded-4 border border-secondary border-opacity-10 overflow-hidden position-relative hover-lift" style="background: linear-gradient(180deg, #1e1e2d 0%, #151521 100%); box-shadow: 0 10px 30px rgba(0,0,0,0.5); transition: all 0.3s ease;">
                        
                        <!-- Gradient Cover -->
                        <div class="artist-cover position-relative" style="height: 120px; background: linear-gradient(135deg, #ff7eb3 0%, #ff758c 100%);">
                            <div class="position-absolute w-100 h-100" style="background: linear-gradient(to bottom, rgba(21,21,33,0) 0%, rgba(21,21,33,1) 100%); top: 0; left: 0;"></div>
                        </div>
                        
                        <!-- Avatar overlapping cover -->
                        <div class="artist-avatar-wrapper text-center" style="margin-top: -60px; position: relative; z-index: 2;">
                            <?php 
                            $img_src = !empty($musician['profile_image']) && $musician['profile_image'] !== 'default_avatar.png' 
                                ? 'uploads/avatars/' . $musician['profile_image'] 
                                : 'https://ui-avatars.com/api/?name='.urlencode($musician['username']).'&background=2a2a35&color=fff';
                            ?>
                            <img src="<?php echo htmlspecialchars($img_src); ?>" class="rounded-circle shadow-lg" style="width: 120px; height: 120px; object-fit: cover; border: 4px solid #151521; background: #1a1a2e;" alt="Profile">
                        </div>
                        
                        <div class="card-body text-center d-flex flex-column pt-3 pb-4 px-4">
                            <?php 
                            $display_name = $musician['username'];
                            if ($musician['band_type'] === 'solo' && !empty($musician['first_name'])) {
                                $display_name = $musician['first_name'] . ' ' . $musician['last_name'];
                            } elseif ($musician['band_type'] === 'band') {
                                $band_data = json_decode($musician['band_members'], true);
                                if (!empty($band_data['band_name'])) {
                                    $display_name = $band_data['band_name'];
                                }
                            }
                            ?>
                            <h4 class="fw-bolder text-white text-truncate mb-2" title="<?php echo htmlspecialchars($display_name); ?>" style="letter-spacing: 0.5px;">
                                <?php echo htmlspecialchars($display_name); ?>
                            </h4>
                            
                            <div class="mb-3">
                                <span class="badge rounded-pill" style="background: linear-gradient(45deg, #8a2387, #e94057); color: #fff; font-weight: 500; padding: 6px 16px; letter-spacing: 0.5px; box-shadow: 0 4px 10px rgba(233, 64, 87, 0.3);">
                                    <?php echo $musician['band_type'] === 'solo' ? 'ศิลปินเดี่ยว' : 'วงดนตรี'; ?>
                                </span>
                            </div>
                            
                            <div class="d-flex justify-content-center align-items-center mb-4">
                                <div class="me-2" style="color: #ffc107;">
                                    <?php 
                                    $score = $musician['rating_score'] ?: 0;
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($score >= $i) echo '<i class="fas fa-star" style="filter: drop-shadow(0 0 5px rgba(255,193,7,0.5));"></i>';
                                        else if ($score >= $i - 0.5) echo '<i class="fas fa-star-half-alt" style="filter: drop-shadow(0 0 5px rgba(255,193,7,0.5));"></i>';
                                        else echo '<i class="far fa-star text-secondary opacity-25"></i>';
                                    }
                                    ?>
                                </div>
                                <span class="text-white fw-bold fs-5"><?php echo number_format($score, 1); ?></span>
                            </div>
                            
                            <div class="text-start mt-auto w-100 mx-auto" style="max-width: 250px;">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 32px; height: 32px; min-width: 32px; background: rgba(230, 133, 255, 0.1);">
                                        <i class="fas fa-music" style="color: #e685ff; font-size: 0.85rem;"></i>
                                    </div>
                                    <span class="text-light text-truncate" style="font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($musician['genres'] ?: 'ไม่ได้ระบุ'); ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 32px; height: 32px; min-width: 32px; background: rgba(255, 75, 75, 0.1);">
                                        <i class="fas fa-map-marker-alt" style="color: #ff4b4b; font-size: 0.85rem;"></i>
                                    </div>
                                    <span class="text-light text-truncate" style="font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($musician['location'] ?: 'ไม่ได้ระบุ'); ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 32px; height: 32px; min-width: 32px; background: rgba(0, 230, 118, 0.1);">
                                        <i class="fas fa-money-bill-wave" style="color: #00e676; font-size: 0.85rem;"></i>
                                    </div>
                                    <span class="text-light fw-bold" style="font-size: 0.9rem;">
                                        เริ่มต้น ฿<?php echo htmlspecialchars($musician['rate'] ?: '0'); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <a href="musician_profile.php?id=<?php echo $musician['user_id']; ?>" class="btn w-100 rounded-pill mt-4 py-2 fw-bold text-white shadow-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(118, 75, 162, 0.6)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 10px rgba(118, 75, 162, 0.4)';">
                                ดูโปรไฟล์ศิลปิน
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Empty State -->
        <div class="d-flex flex-column align-items-center justify-content-center text-center py-5 my-5 bg-dark bg-opacity-25 rounded-4 border border-secondary border-opacity-25">
            <div class="mb-4 p-4 rounded-circle" style="background: rgba(196, 113, 237, 0.1);">
                <i class="fas fa-search-minus fa-3x text-primary" style="filter: drop-shadow(0 0 10px rgba(196, 113, 237, 0.5));"></i>
            </div>
            <h4 class="text-white fw-bold mb-2">ไม่พบศิลปินที่ตรงกับเงื่อนไข</h4>
            <p class="text-secondary mb-4 max-w-md mx-auto">ลองปรับเปลี่ยนคำค้นหา ขยายช่วงราคา หรือลดเงื่อนไขตัวกรองลง เพื่อให้พบผลลัพธ์ที่หลากหลายขึ้น</p>
            <a href="search.php" class="btn btn-primary rounded-pill px-4">ล้างตัวกรองทั้งหมด</a>
        </div>
    <?php endif; ?>
<?php endif; ?>
    
</div>

<!-- Scripts moved to js/script.js -->

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const canvas = document.getElementById('noteCanvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        
        // Resize canvas
        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        window.addEventListener('resize', resize);
        resize();
        
        const notes = [];
        const symbols = ['♪', '♫', '♩', '♬', '♭', '♮', '♯'];
        
        let mouse = { x: -100, y: -100 };
        
        window.addEventListener('mousemove', (e) => {
            mouse.x = e.x;
            mouse.y = e.y;
            
            // Randomly add notes when mouse moves
            if (Math.random() > 0.6) {
                notes.push({
                    x: mouse.x,
                    y: mouse.y,
                    symbol: symbols[Math.floor(Math.random() * symbols.length)],
                    size: Math.random() * 20 + 10,
                    speedX: Math.random() * 2 - 1,
                    speedY: Math.random() * -2 - 1,
                    opacity: 1,
                    color: `hsl(${Math.random() * 60 + 260}, 100%, 70%)` // Purple/pink hues
                });
            }
        });
        
        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            for (let i = 0; i < notes.length; i++) {
                const note = notes[i];
                ctx.fillStyle = note.color;
                ctx.globalAlpha = note.opacity;
                ctx.font = note.size + 'px Arial';
                ctx.fillText(note.symbol, note.x, note.y);
                
                note.x += note.speedX;
                note.y += note.speedY;
                note.opacity -= 0.015; // Fade out
                
                if (note.opacity <= 0) {
                    notes.splice(i, 1);
                    i--;
                }
            }
            ctx.globalAlpha = 1;
            requestAnimationFrame(animate);
        }
        animate();
    });
</script>

<?php include 'includes/footer.php'; ?>

