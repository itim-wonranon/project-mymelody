<?php
require_once 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'musician') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch existing profile
$stmt = $conn->prepare("SELECT * FROM musician_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

if (!$profile) {
    header("Location: become_musician.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $band_type = $_POST['band_type'] ?? 'solo';
    $bio = trim($_POST['bio']);
    $rate_amount = trim($_POST['rate_amount']);
    $pricing_type = $_POST['pricing_type'] ?? 'hour';

    // Process Profile Image
    $profile_image = $profile['profile_image']; // keep existing by default
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['profile_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $image_info = @getimagesize($_FILES['profile_image']['tmp_name']);
            if ($image_info !== false) {
                $new_filename = uniqid() . '.' . $ext;
                $upload_path = 'uploads/avatars/' . $new_filename;
                if (!is_dir('uploads/avatars')) {
                    mkdir('uploads/avatars', 0777, true);
                }
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    $profile_image = $new_filename;
                    $_SESSION['profile_image'] = $profile_image;
                }
            } else {
                $error = "ไฟล์อัปโหลดไม่ใช่รูปภาพที่ถูกต้อง";
            }
        } else {
            $error = "รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF, WEBP)";
        }
    }

    if (empty($error)) {
        // Process Genres
        $genres = $_POST['genres'] ?? [];
        if (!empty(trim($_POST['other_genre']))) {
            $genres[] = trim($_POST['other_genre']);
        }
        $genres_str = implode(', ', $genres);

        // Process Work Areas
        $work_area_type = $_POST['work_area_type'] ?? 'bkk';
        if ($work_area_type === 'bkk') {
            $work_areas = ["กรุงเทพมหานคร", "นนทบุรี", "ปทุมธานี", "สมุทรปราการ", "สมุทรสาคร", "นครปฐม"];
        } elseif ($work_area_type === 'nationwide') {
            $work_areas = ["ทั่วประเทศ"];
        } else {
            $work_areas = $_POST['custom_work_areas'] ?? [];
        }
        $work_areas_json = json_encode($work_areas, JSON_UNESCAPED_UNICODE);

        // Process Availability (Days & Specific Times via calendar JSON)
        $availability_json = $_POST['availability_calendar_json'] ?? '[]';
        $days_json = $availability_json;
        $times_json = json_encode([], JSON_UNESCAPED_UNICODE);

        // Process Band/Solo Specifics
        $age = null;
        $instruments = null;
        $band_members_json = null;

        if ($band_type === 'solo') {
            $age = (int) $_POST['solo_age'];
            $instruments = trim($_POST['solo_instruments']);
        } else {
            $member_names = $_POST['member_name'] ?? [];
            $member_ages = $_POST['member_age'] ?? [];
            $member_insts = $_POST['member_instruments'] ?? [];

            $members = [];
            for ($i = 0; $i < count($member_names); $i++) {
                if (!empty(trim($member_names[$i]))) {
                    $members[] = [
                        'name' => trim($member_names[$i]),
                        'age' => (int) ($member_ages[$i] ?? 0),
                        'instrument' => trim($member_insts[$i] ?? '')
                    ];
                }
            }
            $band_members_json = json_encode([
                'band_name' => trim($_POST['band_name']),
                'members' => $members
            ], JSON_UNESCAPED_UNICODE);
        }

        try {
            $stmt = $conn->prepare("UPDATE musician_profiles SET 
                profile_image = ?, bio = ?, band_type = ?, age = ?, instruments = ?, band_members = ?, genres = ?, rate = ?, pricing_type = ?, work_areas = ?, availability_days = ?, availability_times = ?
                WHERE user_id = ?");

            $stmt->execute([
                $profile_image,
                $bio,
                $band_type,
                $age,
                $instruments,
                $band_members_json,
                $genres_str,
                $rate_amount,
                $pricing_type,
                $work_areas_json,
                $days_json,
                $times_json,
                $user_id
            ]);

            $success = "อัปเดตข้อมูลศิลปินสำเร็จ";

            // Refresh profile data
            $stmt = $conn->prepare("SELECT * FROM musician_profiles WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $profile = $stmt->fetch();

        } catch (Exception $e) {
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// Decode JSONs for form population
$selected_genres = array_map('trim', explode(',', $profile['genres']));
$selected_areas = json_decode($profile['work_areas'], true) ?? [];
$selected_days = json_decode($profile['availability_days'], true) ?? [];
$band_data = json_decode($profile['band_members'], true) ?? ['band_name' => '', 'members' => []];
?>
<?php include 'includes/header.php'; ?>

<style>
/* Custom Premium Tabs */
.premium-tabs {
    border-bottom: 2px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 2rem;
}
.premium-tabs .nav-item {
    margin-bottom: -2px;
}
.premium-tabs .nav-link {
    color: rgba(255, 255, 255, 0.6);
    border: none;
    border-bottom: 2px solid transparent;
    border-radius: 0;
    padding: 1rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s ease;
    background: transparent;
}
.premium-tabs .nav-link:hover {
    color: #fff;
    border-bottom-color: rgba(255, 255, 255, 0.3);
}
.premium-tabs .nav-link.active {
    color: var(--primary-color);
    background: transparent;
    border-bottom-color: var(--primary-color);
    text-shadow: 0 0 10px rgba(0, 243, 255, 0.5);
}
.tab-content-card {
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 15px 35px rgba(0,0,0,0.3);
    animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Image Upload Circle */
.profile-upload-wrapper {
    position: relative;
    width: 150px;
    height: 150px;
    margin: 0 auto;
    border-radius: 50%;
    border: 3px solid var(--primary-color);
    box-shadow: 0 0 20px rgba(0, 243, 255, 0.3);
    overflow: hidden;
    cursor: pointer;
    background: #000;
    transition: all 0.3s ease;
}
.profile-upload-wrapper:hover {
    transform: scale(1.05);
    box-shadow: 0 0 30px rgba(0, 243, 255, 0.6);
}
.profile-upload-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.profile-upload-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: 0.3s;
}
.profile-upload-wrapper:hover .profile-upload-overlay {
    opacity: 1;
}
</style>

<div class="container py-5 mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="text-center mb-4">
                <h2 class="fw-bold text-white mb-2"><i class="fas fa-user-edit text-primary me-2"></i>อัปเดตโปรไฟล์ศิลปิน</h2>
                <p class="text-secondary">แก้ไขข้อมูลและประวัติของคุณให้โดดเด่นน่าสนใจยิ่งขึ้น</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger bg-danger bg-opacity-20 border-danger text-white border-opacity-30"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success bg-success bg-opacity-20 border-success text-white border-opacity-30"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
            <?php endif; ?>

            <form action="edit_musician.php" method="POST" enctype="multipart/form-data" id="musicianForm">
                
                <!-- Tab Navigation -->
                <ul class="nav nav-tabs premium-tabs" id="editProfileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab" aria-controls="basic" aria-selected="true"><i class="fas fa-info-circle me-2"></i>ข้อมูลพื้นฐาน</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="style-tab" data-bs-toggle="tab" data-bs-target="#style" type="button" role="tab" aria-controls="style" aria-selected="false"><i class="fas fa-music me-2"></i>สไตล์ & พื้นที่</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="bio-tab" data-bs-toggle="tab" data-bs-target="#bio" type="button" role="tab" aria-controls="bio" aria-selected="false"><i class="fas fa-id-card me-2"></i>ประวัติ & คิวงาน</button>
                    </li>
                </ul>

                <div class="tab-content" id="editProfileTabsContent">
                    
                    <!-- TAB 1: BASIC INFO -->
                    <div class="tab-pane fade show active" id="basic" role="tabpanel" aria-labelledby="basic-tab">
                        <div class="tab-content-card">
                            
                            <!-- Profile Picture -->
                            <div class="text-center mb-5">
                                <h5 class="text-white mb-3 fw-bold">รูปโปรไฟล์ศิลปิน</h5>
                                <div class="profile-upload-wrapper" onclick="document.getElementById('profile_image').click()">
                                    <?php 
                                        $img_src = !empty($profile['profile_image']) && $profile['profile_image'] !== 'default_avatar.png' ? 'uploads/avatars/' . $profile['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($_SESSION['username']).'&background=0D8ABC&color=fff';
                                    ?>
                                    <img src="<?php echo $img_src; ?>" id="profilePreview" class="profile-upload-image" alt="Profile">
                                    <div class="profile-upload-overlay">
                                        <i class="fas fa-camera text-white fa-2x mb-2"></i>
                                        <span class="text-white small fw-bold">เปลี่ยนรูป</span>
                                    </div>
                                </div>
                                <input type="file" id="profile_image" name="profile_image" class="d-none" accept="image/*" onchange="previewImage(this)">
                            </div>

                            <!-- Artist Type -->
                            <h5 class="text-white fw-bold mb-3"><i class="fas fa-users text-cyan me-2"></i>ประเภทศิลปิน</h5>
                            <div class="row mb-4">
                                <div class="col-md-4 mb-3">
                                    <input type="radio" class="role-radio-hidden" name="band_type" id="type_solo" value="solo" <?php echo ($profile['band_type'] == 'solo' ? 'checked' : ''); ?> onchange="toggleBandFields()">
                                    <label class="role-card-premium w-100 py-3 text-center" for="type_solo">
                                        <i class="fas fa-user fa-2x mb-2 d-block"></i>ศิลปินเดี่ยว
                                    </label>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <input type="radio" class="role-radio-hidden" name="band_type" id="type_duo" value="duo" <?php echo ($profile['band_type'] == 'duo' ? 'checked' : ''); ?> onchange="toggleBandFields()">
                                    <label class="role-card-premium w-100 py-3 text-center" for="type_duo">
                                        <i class="fas fa-user-friends fa-2x mb-2 d-block"></i>ศิลปินคู่
                                    </label>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <input type="radio" class="role-radio-hidden" name="band_type" id="type_band" value="band" <?php echo ($profile['band_type'] == 'band' ? 'checked' : ''); ?> onchange="toggleBandFields()">
                                    <label class="role-card-premium w-100 py-3 text-center" for="type_band">
                                        <i class="fas fa-users fa-2x mb-2 d-block"></i>วงดนตรี (3+)
                                    </label>
                                </div>
                            </div>

                            <!-- Solo Fields -->
                            <div id="solo_fields" class="dynamic-section" style="display: <?php echo ($profile['band_type'] == 'solo' ? 'block' : 'none'); ?>;">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-secondary">อายุ</label>
                                        <input type="number" class="form-control" name="solo_age" min="1" value="<?php echo htmlspecialchars($profile['age'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-secondary">เครื่องดนตรีที่ถนัด</label>
                                        <input type="text" class="form-control" name="solo_instruments" value="<?php echo htmlspecialchars($profile['instruments'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Duo/Band Fields -->
                            <div id="band_fields" class="dynamic-section" style="display: <?php echo ($profile['band_type'] != 'solo' ? 'block' : 'none'); ?>;">
                                <div class="mb-4">
                                    <label class="form-label text-secondary">ชื่อวงดนตรี</label>
                                    <input type="text" class="form-control" name="band_name" value="<?php echo htmlspecialchars($band_data['band_name'] ?? ''); ?>">
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <label class="form-label text-secondary mb-0">สมาชิกวง</label>
                                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill" onclick="addMemberField()"><i class="fas fa-plus me-1"></i>เพิ่มสมาชิก</button>
                                </div>
                                <div id="members_container">
                                    <?php
                                    if (!empty($band_data['members'])):
                                        foreach ($band_data['members'] as $m): ?>
                                            <div class="row mb-3 member-row align-items-center">
                                                <div class="col-md-4 mb-2 mb-md-0"><input type="text" class="form-control" name="member_name[]" value="<?php echo htmlspecialchars($m['name']); ?>" placeholder="ชื่อ"></div>
                                                <div class="col-md-3 mb-2 mb-md-0"><input type="number" class="form-control" name="member_age[]" value="<?php echo htmlspecialchars($m['age']); ?>" placeholder="อายุ"></div>
                                                <div class="col-md-4 mb-2 mb-md-0"><input type="text" class="form-control" name="member_instruments[]" value="<?php echo htmlspecialchars($m['instrument']); ?>" placeholder="เครื่องดนตรี"></div>
                                                <div class="col-md-1 text-end"><button type="button" class="btn btn-outline-danger" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-trash"></i></button></div>
                                            </div>
                                        <?php endforeach;
                                    else: ?>
                                        <div class="row mb-3 member-row align-items-center">
                                            <div class="col-md-4 mb-2 mb-md-0"><input type="text" class="form-control" name="member_name[]" placeholder="ชื่อ"></div>
                                            <div class="col-md-3 mb-2 mb-md-0"><input type="number" class="form-control" name="member_age[]" placeholder="อายุ" min="1"></div>
                                            <div class="col-md-4 mb-2 mb-md-0"><input type="text" class="form-control" name="member_instruments[]" placeholder="เครื่องดนตรี"></div>
                                            <div class="col-md-1 text-end"><button type="button" class="btn btn-outline-danger" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-trash"></i></button></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="text-end mt-4">
                                <button type="button" class="btn btn-outline-primary px-4 rounded-pill" onclick="document.getElementById('style-tab').click()">ถัดไป <i class="fas fa-arrow-right ms-2"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: STYLE & AREAS -->
                    <div class="tab-pane fade" id="style" role="tabpanel" aria-labelledby="style-tab">
                        <div class="tab-content-card">
                            
                            <!-- Genres -->
                            <h5 class="text-white fw-bold mb-3"><i class="fas fa-music text-pink me-2"></i>แนวเพลงที่ถนัด</h5>
                            <div class="checkbox-grid custom-checkbox mb-4">
                                <?php
                                $genresList = ['Pop', 'Rock', 'Jazz', 'Acoustic', 'R&B', 'Hip Hop', 'EDM', 'ลูกทุ่ง/เพื่อชีวิต', 'Classic'];
                                $custom_genre = '';
                                foreach ($genresList as $g):
                                    $checked = in_array($g, $selected_genres) ? 'checked' : '';
                                    if ($checked) {
                                        $selected_genres = array_diff($selected_genres, [$g]);
                                    }
                                    ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="genres[]" value="<?php echo htmlspecialchars($g); ?>" id="genre_<?php echo md5($g); ?>" <?php echo $checked; ?>>
                                        <label class="form-check-label text-light" for="genre_<?php echo md5($g); ?>"><?php echo htmlspecialchars($g); ?></label>
                                    </div>
                                <?php endforeach; 
                                if (!empty($selected_genres)) {
                                    $custom_genre = implode(', ', $selected_genres);
                                }
                                ?>
                            </div>
                            <div class="mb-5">
                                <label class="form-label text-secondary">แนวเพลงอื่นๆ (โปรดระบุ)</label>
                                <input type="text" class="form-control" name="other_genre" value="<?php echo htmlspecialchars($custom_genre); ?>" placeholder="เช่น Blues, Reggae">
                            </div>

                            <!-- Pricing -->
                            <h5 class="text-white fw-bold mb-3"><i class="fas fa-tags text-warning me-2"></i>เรทราคา</h5>
                            <div class="row mb-5">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-secondary">จำนวนเงิน (บาท)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-dark border-secondary text-white">฿</span>
                                        <input type="number" class="form-control" name="rate_amount" value="<?php echo htmlspecialchars($profile['rate']); ?>" required min="0" step="100" placeholder="เช่น 2000">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-secondary">รูปแบบการคิดเงิน</label>
                                    <select class="form-select" name="pricing_type">
                                        <option value="hour" <?php echo ($profile['pricing_type'] == 'hour' ? 'selected' : ''); ?>>ต่อชั่วโมง</option>
                                        <option value="day" <?php echo ($profile['pricing_type'] == 'day' ? 'selected' : ''); ?>>ต่องาน/ต่อวัน</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Work Areas -->
                            <h5 class="text-white fw-bold mb-3"><i class="fas fa-map-marker-alt text-success me-2"></i>พื้นที่รับงาน</h5>
                            <?php 
                            $bkk_set = ["กรุงเทพมหานคร", "นนทบุรี", "ปทุมธานี", "สมุทรปราการ", "สมุทรสาคร", "นครปฐม"];
                            $is_bkk = false;
                            $is_nationwide = false;
                            $is_custom = false;
                            if (count($selected_areas) === 1 && $selected_areas[0] === "ทั่วประเทศ") {
                                $is_nationwide = true;
                            } elseif (count(array_intersect($bkk_set, $selected_areas)) === count($bkk_set) && count($selected_areas) === count($bkk_set)) {
                                $is_bkk = true;
                            } else {
                                $is_custom = true;
                            }
                            ?>
                            <div class="row mb-4">
                                <div class="col-md-4 mb-3">
                                    <input type="radio" class="role-radio-hidden" name="work_area_type" id="wa_bkk" value="bkk" <?php echo $is_bkk ? 'checked' : ''; ?> onchange="toggleCustomArea()">
                                    <label class="role-card-premium w-100 py-3 text-center" for="wa_bkk">
                                        <i class="fas fa-city fa-2x mb-2 d-block text-info"></i>กทม. และปริมณฑล
                                    </label>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <input type="radio" class="role-radio-hidden" name="work_area_type" id="wa_nationwide" value="nationwide" <?php echo $is_nationwide ? 'checked' : ''; ?> onchange="toggleCustomArea()">
                                    <label class="role-card-premium w-100 py-3 text-center" for="wa_nationwide">
                                        <i class="fas fa-globe-asia fa-2x mb-2 d-block text-warning"></i>รับงานทั่วประเทศ
                                    </label>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <input type="radio" class="role-radio-hidden" name="work_area_type" id="wa_custom" value="custom" <?php echo $is_custom ? 'checked' : ''; ?> onchange="toggleCustomArea()">
                                    <label class="role-card-premium w-100 py-3 text-center" for="wa_custom">
                                        <i class="fas fa-map-pin fa-2x mb-2 d-block text-danger"></i>เลือกเฉพาะบางจังหวัด
                                    </label>
                                </div>
                            </div>

                            <div id="custom_area_section" class="dynamic-section mb-4" style="display: <?php echo $is_custom ? 'block' : 'none'; ?>;">
                                <label class="form-label text-secondary mb-2">ค้นหาและเลือกจังหวัด (พิมพ์ชื่อจังหวัดได้เลย)</label>
                                <select class="form-select select2-provinces" name="custom_work_areas[]" multiple="multiple" style="width: 100%;">
                                    <?php 
                                    $thai_provinces = ["กระบี่", "กรุงเทพมหานคร", "กาญจนบุรี", "กาฬสินธุ์", "กำแพงเพชร", "ขอนแก่น", "จันทบุรี", "ฉะเชิงเทรา", "ชลบุรี", "ชัยนาท", "ชัยภูมิ", "ชุมพร", "เชียงราย", "เชียงใหม่", "ตรัง", "ตราด", "ตาก", "นครนายก", "นครปฐม", "นครพนม", "นครราชสีมา", "นครศรีธรรมราช", "นครสวรรค์", "นนทบุรี", "นราธิวาส", "น่าน", "บึงกาฬ", "บุรีรัมย์", "ปทุมธานี", "ประจวบคีรีขันธ์", "ปราจีนบุรี", "ปัตตานี", "พระนครศรีอยุธยา", "พะเยา", "พังงา", "พัทลุง", "พิจิตร", "พิษณุโลก", "เพชรบุรี", "เพชรบูรณ์", "แพร่", "ภูเก็ต", "มหาสารคาม", "มุกดาหาร", "แม่ฮ่องสอน", "ยโสธร", "ยะลา", "ร้อยเอ็ด", "ระนอง", "ระยอง", "ราชบุรี", "ลพบุรี", "ลำปาง", "ลำพูน", "เลย", "ศรีสะเกษ", "สกลนคร", "สงขลา", "สตูล", "สมุทรปราการ", "สมุทรสงคราม", "สมุทรสาคร", "สระแก้ว", "สระบุรี", "สิงห์บุรี", "สุโขทัย", "สุพรรณบุรี", "สุราษฎร์ธานี", "สุรินทร์", "หนองคาย", "หนองบัวลำภู", "อ่างทอง", "อำนาจเจริญ", "อุดรธานี", "อุตรดิตถ์", "อุทัยธานี", "อุบลราชธานี"];
                                    foreach ($thai_provinces as $prov): 
                                        $selected = in_array($prov, $selected_areas) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($prov); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($prov); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="d-flex justify-content-between mt-5">
                                <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" onclick="document.getElementById('basic-tab').click()"><i class="fas fa-arrow-left me-2"></i> ก่อนหน้า</button>
                                <button type="button" class="btn btn-outline-primary px-4 rounded-pill" onclick="document.getElementById('bio-tab').click()">ถัดไป <i class="fas fa-arrow-right ms-2"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: BIO & CALENDAR -->
                    <div class="tab-pane fade" id="bio" role="tabpanel" aria-labelledby="bio-tab">
                        <div class="tab-content-card">
                            
                            <h5 class="text-white fw-bold mb-3"><i class="fas fa-id-badge text-info me-2"></i>แนะนำตัวสั้นๆ (Bio)</h5>
                            <div class="mb-5">
                                <textarea class="form-control" name="bio" rows="5" required placeholder="บอกเล่าประสบการณ์และสไตล์การเล่นของคุณให้ผู้ว่าจ้างฟัง..."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                            </div>

                            <h5 class="text-white fw-bold mb-3"><i class="fas fa-calendar-alt text-warning me-2"></i>จัดการคิวงาน (Availability)</h5>
                            <p class="text-secondary small mb-3">คลิกวันที่ว่างเพื่อเปิดรับงาน</p>
                            
                            <div class="card bg-dark border-secondary p-3 mb-4 rounded-4" style="box-shadow: inset 0 0 10px rgba(0,0,0,0.5);">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                                    <h6 class="mb-0 text-white fw-bold" id="currentMonthLabel">Month Year</h6>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
                                </div>
                                
                                <div class="neon-calendar-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; text-align: center; color: var(--primary-color); font-weight: bold; font-size: 0.9rem; margin-bottom: 5px;">
                                    <div>อา</div><div>จ</div><div>อ</div><div>พ</div><div>พฤ</div><div>ศ</div><div>ส</div>
                                </div>
                                
                                <div class="neon-calendar-grid" id="calendarGrid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; text-align: center;">
                                    <!-- Calendar rendered here -->
                                </div>
                                
                                <input type="hidden" name="availability_calendar_json" id="availability_calendar_json"
                                    value="<?php echo htmlspecialchars(is_array($selected_days) ? json_encode($selected_days, JSON_UNESCAPED_UNICODE) : '[]'); ?>">
                            </div>

                            <div class="d-flex justify-content-between mt-5 pt-3 border-top border-secondary border-opacity-25">
                                <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" onclick="document.getElementById('style-tab').click()"><i class="fas fa-arrow-left me-2"></i> ก่อนหน้า</button>
                                <button type="submit" class="btn btn-primary px-5 rounded-pill glow-btn fw-bold shadow-lg"><i class="fas fa-save me-2"></i> บันทึกการแก้ไข</button>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Tab Event Listener to trigger Calendar Render when Bio Tab is opened
    document.getElementById('bio-tab').addEventListener('shown.bs.tab', function (e) {
        if(typeof renderCalendar === 'function') {
            renderCalendar();
        }
    });
</script>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
/* Custom styling for Select2 to match dark theme */
.select2-container {
    width: 100% !important;
}
.select2-container--default .select2-selection--multiple {
    background-color: #212529 !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 8px !important;
    padding: 5px !important;
    width: 100% !important;
    min-height: 45px; /* Ensure it has height even when empty */
}
.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: var(--primary-color) !important;
    border: none !important;
    color: #fff !important;
    border-radius: 20px !important;
    padding: 4px 14px !important;
    margin-top: 5px !important;
    display: inline-flex !important; /* Fixed: inline-flex instead of flex */
    flex-direction: row-reverse; /* Move X to the right */
    align-items: center;
    gap: 8px;
    font-weight: 500;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2); /* Added small shadow for beauty */
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: rgba(255, 255, 255, 0.6) !important;
    border: none !important;
    background: transparent !important;
    position: static !important;
    font-weight: bold;
    padding: 0 !important;
    margin: 0 !important;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
    color: #fff !important;
    background: transparent !important;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__display {
    padding: 0 !important;
}
.select2-dropdown {
    background-color: #212529 !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: #fff !important;
}
.select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
    background-color: var(--primary-color) !important;
    color: white !important;
}
.select2-container--default .select2-results__option--selected {
    background-color: rgba(0, 243, 255, 0.2) !important;
}
.select2-search__field {
    color: #fff !important;
}
</style>
<script>
    function toggleCustomArea() {
        const customRadio = document.getElementById('wa_custom');
        const customSection = document.getElementById('custom_area_section');
        if(customRadio && customRadio.checked) {
            customSection.style.display = 'block';
        } else {
            customSection.style.display = 'none';
        }
    }

    // Initialize Select2 after jQuery is ready
    window.addEventListener('load', function() {
        if(window.jQuery) {
            $('.select2-provinces').select2({
                placeholder: "ค้นหาจังหวัด...",
                allowClear: true,
                width: '100%'
            });
        }
    });

    // Image Preview
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profilePreview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Toggle Band Fields
    function toggleBandFields() {
        const checkedInput = document.querySelector('input[name="band_type"]:checked');
        if (!checkedInput) return;

        const bandType = checkedInput.value;
        const soloFields = document.getElementById('solo_fields');
        const bandFields = document.getElementById('band_fields');

        const inputsSolo = soloFields.querySelectorAll('input');
        const inputsBand = bandFields.querySelectorAll('input');

        if (bandType === 'solo') {
            soloFields.style.display = 'block';
            bandFields.style.display = 'none';
            inputsSolo.forEach(i => i.disabled = false);
            inputsBand.forEach(i => i.disabled = true);
        } else {
            soloFields.style.display = 'none';
            bandFields.style.display = 'block';
            inputsSolo.forEach(i => i.disabled = true);
            inputsBand.forEach(i => i.disabled = false);
        }
    }

    // Add Member Field
    function addMemberField() {
        const container = document.getElementById('members_container');
        if (!container) return;
        const row = document.createElement('div');
        row.className = 'row mb-3 member-row align-items-center animate__animated animate__fadeIn';
        row.innerHTML = `
            <div class="col-md-4 mb-2 mb-md-0"><input type="text" class="form-control" name="member_name[]" placeholder="ชื่อ"></div>
            <div class="col-md-3 mb-2 mb-md-0"><input type="number" class="form-control" name="member_age[]" placeholder="อายุ" min="1"></div>
            <div class="col-md-4 mb-2 mb-md-0"><input type="text" class="form-control" name="member_instruments[]" placeholder="เครื่องดนตรี"></div>
            <div class="col-md-1 text-end"><button type="button" class="btn btn-outline-danger" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-trash"></i></button></div>
        `;
        container.appendChild(row);
    }

    // Initialize toggle state on load
    document.addEventListener('DOMContentLoaded', () => {
        toggleBandFields();
        toggleCustomArea();
        
        // Prevent empty calendar JSON
        document.getElementById('musicianForm').addEventListener('submit', function() {
            const calInput = document.getElementById('availability_calendar_json');
            if(!calInput.value) calInput.value = '[]';
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
