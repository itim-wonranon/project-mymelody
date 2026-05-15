<?php
// community.php
require_once 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 1. Fetch Community Profile
$stmt = $conn->prepare("SELECT * FROM community_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$community_profile = $stmt->fetch();

// 2. Fetch User Stats for Sidebar
$stmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM posts WHERE user_id = ?) as posts_count,
        (SELECT COUNT(*) FROM post_reactions pr JOIN posts p ON pr.post_id = p.id WHERE p.user_id = ?) as reactions_count
");
$stmt->execute([$user_id, $user_id]);
$stats = $stmt->fetch();

// 3. Handle New Post (AJAX or Standard)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_post'])) {
    $content = trim($_POST['content']);
    $type = $_POST['post_type'] ?? 'text';
    $feeling = $_POST['feeling'] ?? null;
    $location = $_POST['location'] ?? null;
    $media_url = null;
    $poll_options = null;
    $poll_question = null;

    // Handle Media Upload
    if (($type === 'image' || $type === 'video') && isset($_FILES['media_file']) && $_FILES['media_file']['error'] == 0) {
        $allowed = $type === 'image' ? ['jpg', 'jpeg', 'png', 'gif', 'webp'] : ['mp4', 'mov', 'avi'];
        $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $filename = uniqid() . '.' . $ext;
            if (!is_dir('uploads/community')) mkdir('uploads/community', 0777, true);
            if (move_uploaded_file($_FILES['media_file']['tmp_name'], 'uploads/community/' . $filename)) {
                $media_url = $filename;
            }
        }
    }

    // Handle Poll
    if ($type === 'poll') {
        $poll_question = trim($_POST['poll_question']);
        $options = $_POST['poll_options'] ?? [];
        $poll_options = json_encode(array_filter($options), JSON_UNESCAPED_UNICODE);
    }

    if (!empty($content) || $media_url || $poll_question) {
        $stmt = $conn->prepare("INSERT INTO posts (user_id, type, content, media_url, feeling, location, poll_question, poll_options) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $type, $content, $media_url, $feeling, $location, $poll_question, $poll_options]);
        header("Location: community.php?success=1");
        exit();
    }
}

// 4. Fetch Posts with filtering and extended info
$filter = $_GET['filter'] ?? 'all';
$sql = "
    SELECT p.*, 
           u.username, u.first_name, u.last_name, u.role,
           m.profile_image as m_img, e.profile_image as e_img, m.band_type, m.band_members,
           cp.display_name as community_name, cp.community_username, cp.avatar as community_avatar,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count,
           (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id) as reactions_total
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN musician_profiles m ON u.id = m.user_id
    LEFT JOIN employer_profiles e ON u.id = e.user_id
    LEFT JOIN community_profiles cp ON u.id = cp.user_id
";

$search = $_GET['search'] ?? '';
$search_query = !empty($search) ? " WHERE p.content LIKE ? " : "";

if ($filter === 'trending') {
    $sql = "
        SELECT p.*, 
               u.username, u.first_name, u.last_name, u.role,
               m.profile_image as m_img, e.profile_image as e_img, m.band_type, m.band_members,
               cp.display_name as community_name, cp.community_username, cp.avatar as community_avatar,
               (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count,
               (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id) as reactions_total,
               ( (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) + 
                 (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) ) as hourly_activity
        FROM posts p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN musician_profiles m ON u.id = m.user_id
        LEFT JOIN employer_profiles e ON u.id = e.user_id
        LEFT JOIN community_profiles cp ON u.id = cp.user_id
        $search_query
        ORDER BY hourly_activity DESC, reactions_total DESC, p.created_at DESC
    ";
} else {
    $sql .= $search_query;
    if ($filter === 'popular') {
        $sql .= " ORDER BY reactions_total DESC, p.created_at DESC";
    } else {
        $sql .= " ORDER BY p.created_at DESC";
    }
}

$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $stmt->execute(['%' . $search . '%']);
} else {
    $stmt->execute();
}
$posts = $stmt->fetchAll();

// 5. Fetch Trending Topics (Based on Hashtags in recent posts)
// We look for words starting with # in the last 100 posts
$stmt = $conn->prepare("SELECT content FROM posts ORDER BY created_at DESC LIMIT 100");
$stmt->execute();
$recent_contents = $stmt->fetchAll(PDO::FETCH_COLUMN);
$hashtags = [];
foreach ($recent_contents as $content) {
    preg_match_all('/#([^\s#]+)/u', $content, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $tag) {
            $hashtags[$tag] = ($hashtags[$tag] ?? 0) + 1;
        }
    }
}
arsort($hashtags);
$trending_tags = array_slice($hashtags, 0, 10, true);

// 6. Fetch Recommended Artists of the Day (Top Rated Today)
$today = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT u.id as user_id, u.username, u.first_name, u.last_name, 
           m.profile_image, m.genres, m.rating_score, m.band_type, m.band_members,
           AVG(r.rating) as today_avg_rating, COUNT(r.id) as review_count
    FROM users u
    JOIN musician_profiles m ON u.id = m.user_id
    JOIN reviews r ON u.id = r.musician_id
    WHERE DATE(r.created_at) = ?
    GROUP BY u.id
    ORDER BY today_avg_rating DESC, review_count DESC
    LIMIT 10
");
$stmt->execute([$today]);
$top_artists_today = $stmt->fetchAll();

// Fallback if no reviews today: Top rated of all time
if (empty($top_artists_today)) {
    $stmt = $conn->prepare("
        SELECT u.id as user_id, u.username, u.first_name, u.last_name, 
               m.profile_image, m.genres, m.rating_score, m.band_type, m.band_members
        FROM users u
        JOIN musician_profiles m ON u.id = m.user_id
        WHERE u.role = 'musician' AND m.is_verified = 1
        ORDER BY m.rating_score DESC
        LIMIT 10
    ");
    $stmt->execute();
    $top_artists_today = $stmt->fetchAll();
}

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="community-layout">
        <!-- Left Sidebar: Nav & Profile -->
        <aside class="community-left">
            <?php include 'includes/community_sidebar.php'; ?>
        </aside>

        <!-- Main Content: Feed -->
        <main class="community-main">
            <?php if ($filter === 'events'): ?>
                <!-- Events Header -->
                <div class="glass-card p-4 mb-4 d-flex justify-content-between align-items-center animate__animated animate__fadeIn">
                    <div>
                        <h4 class="fw-bold text-white mb-1"><i class="fas fa-calendar-alt text-warning me-2"></i>กิจกรรมชุมชน</h4>
                        <p class="text-secondary small mb-0">ค้นหาและร่วมสนุกกับกิจกรรมคนดนตรีใกล้คุณ</p>
                    </div>
                    <button class="btn btn-warning rounded-pill px-4 fw-bold shadow-glow" data-bs-toggle="modal" data-bs-target="#createEventModal">
                        <i class="fas fa-plus me-2"></i>สร้างกิจกรรม
                    </button>
                </div>

                <!-- Events List -->
                <?php
                $current_user_id = $_SESSION['user_id'] ?? 0;
                $stmt = $conn->prepare("
                    SELECT e.*, u.username, u.first_name, u.last_name,
                           (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id) as joined_count,
                           (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id AND user_id = ?) as is_joined
                    FROM community_events e
                    JOIN users u ON e.user_id = u.id
                    ORDER BY e.event_date ASC
                ");
                $stmt->execute([$current_user_id]);
                $events = $stmt->fetchAll();
                ?>

                <div class="row g-4 animate__animated animate__fadeIn">
                    <?php if (empty($events)): ?>
                        <div class="col-12 text-center p-5 text-muted glass-card">
                            <i class="fas fa-calendar-times fa-3x mb-3 opacity-25"></i>
                            <p>ยังไม่มีกิจกรรมในขณะนี้</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($events as $ev): ?>
                            <div class="col-md-6">
                                <div class="glass-card p-0 overflow-hidden h-100 border border-secondary border-opacity-25 hover-glow">
                                    <div class="event-img-placeholder bg-dark d-flex align-items-center justify-content-center" style="height: 160px; background: linear-gradient(45deg, #6a11cb, #2575fc);">
                                        <?php if ($ev['image_url']): ?>
                                            <img src="uploads/events/<?php echo $ev['image_url']; ?>" class="w-100 h-100 object-fit-cover">
                                        <?php else: ?>
                                            <i class="fas fa-music fa-3x text-white opacity-25"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="fw-bold text-white mb-0"><?php echo htmlspecialchars($ev['title']); ?></h5>
                                            <span class="badge bg-dark border border-secondary"><?php echo $ev['joined_count']; ?> คน</span>
                                        </div>
                                        <div class="small text-secondary mb-3">
                                            <div><i class="fas fa-clock text-warning me-2"></i> <?php echo date('d M Y, H:i', strtotime($ev['event_date'])); ?></div>
                                            <div><i class="fas fa-map-marker-alt text-danger me-2"></i> <?php echo htmlspecialchars($ev['location']); ?></div>
                                        </div>
                                        <p class="text-light small text-truncate-2 mb-3"><?php echo htmlspecialchars($ev['description']); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-secondary">โดย @<?php echo htmlspecialchars($ev['username']); ?></small>
                                            <?php if ($ev['is_joined']): ?>
                                                <button class="btn btn-sm btn-outline-success rounded-pill px-3 disabled"><i class="fas fa-check me-1"></i>เข้าร่วมแล้ว</button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-primary rounded-pill px-3" onclick="joinEvent(<?php echo $ev['id']; ?>, this)">เข้าร่วมงาน</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Post Creator (Only for Feed) -->
                <div class="post-creator-card shadow-lg mb-4">
                    <form action="ajax_community.php?action=post" method="POST" enctype="multipart/form-data">
                        <div class="post-input-wrapper">
                            <img src="uploads/avatars/<?php echo htmlspecialchars($community_profile['avatar'] ?? 'default_avatar.png'); ?>" class="post-input-avatar">
                            <textarea name="content" class="post-input-field" placeholder="วันนี้มีอะไรน่าสนใจในโลกดนตรีบ้าง?..." rows="2"></textarea>
                        </div>
                        
                        <!-- Media Preview -->
                        <div id="mediaPreview" class="mb-3 d-none">
                            <div class="position-relative d-inline-block">
                                <img src="" id="imgPreview" class="img-fluid rounded-3" style="max-height: 300px;">
                                <video src="" id="videoPreview" class="img-fluid rounded-3 d-none" style="max-height: 300px;" controls></video>
                                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-2 bg-dark rounded-circle p-2" onclick="clearMedia()"></button>
                            </div>
                        </div>

                        <!-- Poll Area -->
                        <div id="pollArea" class="mb-3 d-none p-3 border border-secondary rounded-3 bg-dark bg-opacity-25">
                            <input type="text" name="poll_question" class="form-control bg-transparent border-0 text-white fw-bold mb-2" placeholder="ตั้งคำถามโพลล์...">
                            <div id="pollOptions">
                                <input type="text" name="poll_options[]" class="form-control form-control-sm bg-dark border-secondary text-white mb-2" placeholder="ตัวเลือกที่ 1">
                                <input type="text" name="poll_options[]" class="form-control form-control-sm bg-dark border-secondary text-white mb-2" placeholder="ตัวเลือกที่ 2">
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addPollOption()">+ เพิ่มตัวเลือก</button>
                        </div>

                        <!-- Extra Info -->
                        <div id="extraInfoArea" class="mb-3 d-none">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-dark border-secondary text-warning"><i class="fas fa-smile"></i></span>
                                        <input type="text" name="feeling" class="form-control bg-dark border-secondary text-white" placeholder="วันนี้รู้สึกอย่างไร?">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-dark border-secondary text-danger"><i class="fas fa-map-marker-alt"></i></span>
                                        <input type="text" name="location" class="form-control bg-dark border-secondary text-white" placeholder="เช็คอินที่ไหนดี?">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="post-actions-bar">
                            <div class="d-flex gap-1">
                                <button type="button" class="action-btn" onclick="triggerFileInput('image')"><i class="fas fa-image text-success"></i></button>
                                <button type="button" class="action-btn" onclick="triggerFileInput('video')"><i class="fas fa-video text-danger"></i></button>
                                <button type="button" class="action-btn" onclick="togglePoll()"><i class="fas fa-poll text-info"></i></button>
                                <button type="button" class="action-btn" onclick="toggleExtraInfo()"><i class="fas fa-smile text-warning"></i></button>
                            </div>
                            <input type="file" id="mediaInput" name="media_file" class="d-none" onchange="handleFileSelect(this)">
                            <input type="hidden" name="type" id="postTypeInput" value="text">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-glow">โพสต์เลย</button>
                        </div>
                    </form>
                </div>

                <!-- Feed Area -->
                <div class="posts-feed animate__animated animate__fadeIn">
                    <?php if (empty($posts)): ?>
                        <div class="text-center py-5 text-muted glass-card">
                            <i class="fas fa-music fa-3x mb-3 opacity-25"></i>
                            <h5>ยังไม่มีความเคลื่อนไหวในขณะนี้</h5>
                            <p>มาเริ่มแชร์เรื่องราวคนดนตรีคนแรกกันเลย!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                            <?php include 'includes/community_post_card.php'; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>

        <!-- Right Sidebar: Trends & Pulse -->
        <aside class="community-right">
            <div class="glass-card p-4 mb-4">
                <h5 class="fw-bold text-white mb-3"><i class="fas fa-bolt text-warning me-2"></i>กำลังมาแรง</h5>
                <div class="trending-list" id="trendingList">
                    <?php if (empty($trending_tags)): ?>
                        <div class="text-muted small">ยังไม่มีเทรนด์ในขณะนี้</div>
                    <?php else: ?>
                        <?php $rank = 1; foreach ($trending_tags as $tag => $count): ?>
                            <div class="trending-item mb-3 <?php echo $rank > 5 ? 'd-none extra-trending' : ''; ?>">
                                <a href="community.php?search=<?php echo urlencode('#' . $tag); ?>" class="text-decoration-none">
                                    <small class="text-secondary">#<?php echo $rank; ?> Trending</small>
                                    <h6 class="mb-0 fw-bold text-white hover-primary">#<?php echo htmlspecialchars($tag); ?></h6>
                                    <small class="text-muted"><?php echo $count; ?> โพสต์</small>
                                </a>
                            </div>
                            <?php $rank++; ?>
                        <?php endforeach; ?>
                        <?php if (count($trending_tags) > 5): ?>
                            <button class="btn btn-link btn-sm text-primary p-0 text-decoration-none" onclick="toggleExtra('trending', this)">ดูเพิ่มเติม</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="glass-card p-4">
                <h5 class="fw-bold text-white mb-3"><i class="fas fa-star text-primary me-2"></i>ศิลปินแนะนำวันนี้</h5>
                <div class="recommended-artists-sidebar" id="artistList">
                    <?php if (empty($top_artists_today)): ?>
                        <div class="text-muted small">ยังไม่มีศิลปินแนะนำ</div>
                    <?php else: ?>
                        <?php $art_count = 1; foreach ($top_artists_today as $art): ?>
                            <?php 
                            $art_name = $art['username'];
                            if ($art['band_type'] === 'solo') {
                                if (!empty($art['first_name'])) $art_name = $art['first_name'] . ' ' . $art['last_name'];
                            } else {
                                $bd = json_decode($art['band_members'], true);
                                if (!empty($bd['band_name'])) $art_name = $bd['band_name'];
                            }
                            $art_img = !empty($art['profile_image']) && $art['profile_image'] !== 'default_avatar.png' ? 'uploads/avatars/' . $art['profile_image'] : 'https://ui-avatars.com/api/?name=' . urlencode($art['username']) . '&background=c471ed&color=fff';
                            ?>
                            <div class="d-flex align-items-center mb-3 <?php echo $art_count > 5 ? 'd-none extra-artists' : ''; ?>">
                                <img src="<?php echo htmlspecialchars($art_img); ?>" class="profile-img-small me-2" style="width: 40px; height: 40px; border: 1px solid var(--primary-color);">
                                <div class="overflow-hidden">
                                    <h6 class="mb-0 fw-bold text-white text-truncate" style="font-size: 0.85rem;"><?php echo htmlspecialchars($art_name); ?></h6>
                                    <small class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($art['genres']); ?> • <?php echo number_format($art['rating_score'], 1); ?> ⭐</small>
                                </div>
                                <a href="musician_profile.php?id=<?php echo $art['user_id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill ms-auto" style="font-size: 0.65rem;">ดูผลงาน</a>
                            </div>
                            <?php $art_count++; ?>
                        <?php endforeach; ?>
                        <?php if (count($top_artists_today) > 5): ?>
                            <button class="btn btn-link btn-sm text-primary p-0 text-decoration-none" onclick="toggleExtra('artists', this)">ดูเพิ่มเติม</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</div>

<!-- Create Event Modal -->
<div class="modal fade" id="createEventModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white fw-bold">สร้างกิจกรรมใหม่</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="createEventForm" onsubmit="submitEvent(event)">
                    <div class="mb-3">
                        <label class="form-label text-secondary small">หัวข้อกิจกรรม</label>
                        <input type="text" name="title" class="form-control bg-dark border-secondary text-white" required placeholder="เช่น แจมเซสชั่นคืนวันเสาร์">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">รายละเอียด</label>
                        <textarea name="description" class="form-control bg-dark border-secondary text-white" rows="3" placeholder="บอกรายละเอียดกิจกรรม..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary small">วันและเวลา</label>
                            <input type="datetime-local" name="event_date" class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary small">จำนวนผู้เข้าร่วมสูงสุด (0 = ไม่จำกัด)</label>
                            <input type="number" name="max_participants" class="form-control bg-dark border-secondary text-white" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">สถานที่</label>
                        <input type="text" name="location" class="form-control bg-dark border-secondary text-white" required placeholder="ชื่อร้าน หรือ พิกัด">
                    </div>
                    <button type="submit" class="btn btn-warning w-100 fw-bold mt-2">ประกาศกิจกรรม</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- AJAX Reactions & Scripts -->
<script src="js/script.js?v=<?php echo time(); ?>"></script>
<script>
function triggerFileInput(type) {
    document.getElementById('postTypeInput').value = type;
    document.getElementById('mediaInput').click();
}

function handleFileSelect(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        const type = document.getElementById('postTypeInput').value;
        
        reader.onload = function(e) {
            document.getElementById('mediaPreview').classList.remove('d-none');
            if (type === 'image') {
                document.getElementById('imgPreview').src = e.target.result;
                document.getElementById('imgPreview').classList.remove('d-none');
                document.getElementById('videoPreview').classList.add('d-none');
            } else {
                document.getElementById('videoPreview').src = e.target.result;
                document.getElementById('videoPreview').classList.remove('d-none');
                document.getElementById('imgPreview').classList.add('d-none');
            }
        }
        reader.readAsDataURL(file);
    }
}

function clearMedia() {
    document.getElementById('mediaInput').value = '';
    document.getElementById('mediaPreview').classList.add('d-none');
    document.getElementById('postTypeInput').value = 'text';
}

function togglePoll() {
    const area = document.getElementById('pollArea');
    const isHidden = area.classList.toggle('d-none');
    document.getElementById('postTypeInput').value = isHidden ? 'text' : 'poll';
}

function toggleExtraInfo() {
    document.getElementById('extraInfoArea').classList.toggle('d-none');
}

function toggleExtra(type, btn) {
    const selector = type === 'trending' ? '.extra-trending' : '.extra-artists';
    const extras = document.querySelectorAll(selector);
    
    extras.forEach(el => el.classList.toggle('d-none'));
    
    if (btn.innerText === 'ดูเพิ่มเติม') {
        btn.innerText = 'แสดงน้อยลง';
    } else {
        btn.innerText = 'ดูเพิ่มเติม';
    }
}

function addPollOption() {
    const container = document.getElementById('pollOptions');
    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'poll_options[]';
    input.className = 'form-control form-control-sm bg-dark border-secondary text-white mb-2';
    input.placeholder = 'ตัวเลือกเพิ่มเติม';
    container.appendChild(input);
}

<?php 
// Get latest IDs for polling
$latest_post_id = $conn->query("SELECT MAX(id) FROM posts")->fetchColumn() ?: 0;
$latest_notif_id_stmt = $conn->prepare("SELECT MAX(id) FROM community_notifications WHERE user_id = ?");
$latest_notif_id_stmt->execute([$_SESSION['user_id']]);
$l_n_id = $latest_notif_id_stmt->fetchColumn() ?: 0;
?>
initPolling(<?php echo $latest_post_id; ?>, <?php echo $l_n_id; ?>);

// Highlight post if coming from index pulse
window.addEventListener('load', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const postId = urlParams.get('post_id');
    if (postId) {
        setTimeout(() => {
            const postElement = document.getElementById('post-' + postId);
            if (postElement) {
                const navbarHeight = 100; // Buffer for sticky header
                const elementPosition = postElement.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - navbarHeight;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'auto' // Instant jump
                });

                postElement.classList.add('highlight-post');
                setTimeout(() => {
                    postElement.classList.remove('highlight-post');
                }, 4000);
            }
        }, 100); // Faster trigger
    }
});
</script>

<?php include 'includes/footer.php'; ?>

<!-- Reactions Modal -->
<div class="modal fade" id="reactionsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h6 class="modal-title text-white fw-bold">การแสดงความรู้สึก</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="reactionsModalBody" style="max-height: 400px; overflow-y: auto;">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>
