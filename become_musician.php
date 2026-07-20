<?php
require_once 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'musician') {
        header("Location: edit_musician.php");
        exit();
    }
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $band_type = $_POST['band_type'] ?? 'solo';
    $bio = trim($_POST['bio']);
    $rate_amount = trim($_POST['rate_amount']);
    $pricing_type = $_POST['pricing_type'] ?? 'hour';
    
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

    // Process Availability from Neon Calendar JSON payload
    $calendar_json = $_POST['availability_calendar_json'] ?? '[]';
    $availability = json_decode($calendar_json, true) ?? [];

    $days_json = json_encode($availability, JSON_UNESCAPED_UNICODE);
    $times_json = json_encode([], JSON_UNESCAPED_UNICODE); // Kept for backwards schema compatibility


    // Process Profile Image
    $profile_image = 'default_avatar.png';
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['profile_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            // SECURITY PATCH: Verify it is actually an image
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
        }
    }

    if (empty($error)) {
        // Process Band/Solo Specifics
        $age = null;
        $instruments = null;
        $band_members_json = null;

        if ($band_type === 'solo') {
            $age = (int)$_POST['solo_age'];
            $instruments = trim($_POST['solo_instruments']);
        } else {
            // Duo or Band
            $member_names = $_POST['member_name'] ?? [];
            $member_ages = $_POST['member_age'] ?? [];
            $member_insts = $_POST['member_instruments'] ?? [];
            
            $members = [];
            for ($i = 0; $i < count($member_names); $i++) {
                if (!empty(trim($member_names[$i]))) {
                    $members[] = [
                        'name' => trim($member_names[$i]),
                        'age' => (int)($member_ages[$i] ?? 0),
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
            $conn->beginTransaction();

            // Update Users Role
            $stmt = $conn->prepare("UPDATE users SET role = 'musician' WHERE id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['role'] = 'musician';

            // Insert Musician Profile
            $stmt = $conn->prepare("INSERT INTO musician_profiles 
                (user_id, profile_image, bio, band_type, age, instruments, band_members, genres, rate, pricing_type, work_areas, availability_days, availability_times, is_verified) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            
            $stmt->execute([
                $user_id, $profile_image, $bio, $band_type, $age, $instruments, $band_members_json, 
                $genres_str, $rate_amount, $pricing_type, $work_areas_json, $days_json, $times_json
            ]);

            $conn->commit();
            header("Location: portfolio_manager.php?setup=success");
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="text-center mb-5 animate__animated animate__fadeInDown">
                <h1 class="display-4 fw-bold text-white mb-3">ยกระดับเป็นนักดนตรี <span class="text-primary">🎸</span></h1>
                <p class="lead text-secondary">อัปเกรดโปรไฟล์ของคุณเพื่อเริ่มรับงานจากผู้ว่าจ้าง</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger rounded-4"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="wizard-container animate__animated animate__fadeInUp">
                <!-- Progress Indicators -->
                <div class="wizard-progress">
                    <div class="wizard-progress-bar" id="wizardProgressBar" style="width: 0%;"></div>
                    <div class="wizard-step-indicator active" id="indicator1">1</div>
                    <div class="wizard-step-indicator" id="indicator2">2</div>
                    <div class="wizard-step-indicator" id="indicator3">3</div>
                    <div class="wizard-step-indicator" id="indicator4">4</div>
                </div>

                <form action="become_musician.php" method="POST" enctype="multipart/form-data" id="musicianForm">
                    
                    <!-- STEP 1: Basic Info -->
                    <div class="wizard-step active" id="step1">
                        <h4 class="text-white fw-bold mb-4"><i class="fas fa-id-card text-primary me-2"></i> ข้อมูลเบื้องต้น</h4>
                        
                        <div class="text-center mb-4">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username'] ?? 'User'); ?>&background=c471ed&color=fff" id="profile_preview" class="profile-img mb-3 shadow" alt="Profile Preview">
                            <div>
                                <label for="profile_image" class="btn btn-outline-primary rounded-pill px-4">
                                    <i class="fas fa-camera me-2"></i> อัปโหลดรูปโปรไฟล์
                                </label>
                                <input type="file" id="profile_image" name="profile_image" accept="image/*" class="d-none" onchange="previewImage(this)">
                            </div>
                            <small class="text-muted mt-2 d-block">รองรับไฟล์ JPG, PNG, WEBP ขนาดไม่เกิน 40MB</small>
                        </div>

                        <h5 class="text-white mt-5 mb-3">ประเภทศิลปิน</h5>
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <input type="radio" class="role-radio-hidden" name="band_type" id="type_solo" value="solo" checked onchange="toggleBandFields()">
                                <label class="role-card-premium w-100 text-center py-4 px-3 h-100 d-flex flex-column justify-content-center cursor-pointer" for="type_solo">
                                    <i class="fas fa-user fa-2x mb-3 text-info"></i>
                                    <span class="role-title d-block text-white fw-bold mb-1" style="font-size: 1.05rem;">ศิลปินเดี่ยว</span>
                                </label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="radio" class="role-radio-hidden" name="band_type" id="type_duo" value="duo" onchange="toggleBandFields()">
                                <label class="role-card-premium w-100 text-center py-4 px-3 h-100 d-flex flex-column justify-content-center cursor-pointer" for="type_duo">
                                    <i class="fas fa-user-friends fa-2x mb-3 text-warning"></i>
                                    <span class="role-title d-block text-white fw-bold mb-1" style="font-size: 1.05rem;">ศิลปินคู่ (Duo)</span>
                                </label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="radio" class="role-radio-hidden" name="band_type" id="type_band" value="band" onchange="toggleBandFields()">
                                <label class="role-card-premium w-100 text-center py-4 px-3 h-100 d-flex flex-column justify-content-center cursor-pointer" for="type_band">
                                    <i class="fas fa-users fa-2x mb-3 text-success"></i>
                                    <span class="role-title d-block text-white fw-bold mb-1" style="font-size: 1.05rem;">วงดนตรี (Band)</span>
                                </label>
                            </div>
                        </div>

                        <!-- Solo Fields -->
                        <div id="solo_fields" class="dynamic-section" style="display: block;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-secondary">อายุ</label>
                                    <input type="number" class="form-control" name="solo_age" min="1" placeholder="ตัวอย่าง: 25">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-secondary">ตำแหน่ง/เครื่องดนตรีหลัก</label>
                                    <input type="text" class="form-control" name="solo_instruments" placeholder="เช่น กีตาร์, ร้องนำ">
                                </div>
                            </div>
                        </div>

                        <!-- Band Fields -->
                        <div id="band_fields" class="dynamic-section" style="display: none;">
                            <div class="mb-4">
                                <label class="form-label text-secondary">ชื่อวงดนตรี</label>
                                <input type="text" class="form-control" name="band_name" placeholder="ระบุชื่อวง">
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label class="form-label text-secondary mb-0">สมาชิกวง</label>
                                <button type="button" class="btn btn-sm btn-outline-info rounded-pill" onclick="addMemberField()"><i class="fas fa-plus me-1"></i> เพิ่มสมาชิก</button>
                            </div>
                            <div id="members_container">
                                <!-- Members dynamically added here via script.js -->
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: Styles -->
                    <div class="wizard-step" id="step2">
                        <h4 class="text-white fw-bold mb-4"><i class="fas fa-music text-primary me-2"></i> สไตล์ดนตรี</h4>
                        
                        <label class="form-label text-secondary mb-3">แนวเพลงที่ถนัด (เลือกได้มากกว่า 1)</label>
                        <div class="checkbox-grid mb-4 custom-checkbox">
                            <?php 
                            $genresList = ['Pop', 'Rock', 'Jazz', 'Acoustic', 'R&B', 'Hip Hop', 'EDM', 'ลูกทุ่ง/เพื่อชีวิต', 'Classic'];
                            foreach ($genresList as $g): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="genres[]" value="<?php echo htmlspecialchars($g); ?>" id="genre_<?php echo md5($g); ?>">
                                    <label class="form-check-label text-light" for="genre_<?php echo md5($g); ?>"><?php echo htmlspecialchars($g); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-secondary">แนวเพลงอื่นๆ (หากไม่มีในตัวเลือก)</label>
                            <input type="text" class="form-control" name="other_genre" placeholder="ระบุแนวเพลงอื่นๆ...">
                        </div>
                    </div>

                    <!-- STEP 3: Work & Pricing -->
                    <div class="wizard-step" id="step3">
                        <h4 class="text-white fw-bold mb-4"><i class="fas fa-money-bill-wave text-primary me-2"></i> การรับงาน & ค่าจ้าง</h4>
                        
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-secondary">เรทราคา (บาท)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary text-white">฿</span>
                                    <input type="number" class="form-control" name="rate_amount" placeholder="ระบุราคาตัวเลข" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-secondary">รูปแบบการคิดราคา</label>
                                <select class="form-select" name="pricing_type">
                                    <option value="hour">รายชั่วโมง</option>
                                    <option value="day">รายวัน (หรืองานเหมา)</option>
                                </select>
                            </div>
                        </div>

                        <h5 class="text-white fw-bold mb-3 mt-4"><i class="fas fa-map-marker-alt text-success me-2"></i> พื้นที่รับงาน</h5>
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <input type="radio" class="role-radio-hidden" name="work_area_type" id="wa_bkk" value="bkk" checked onchange="toggleCustomArea()">
                                <label class="role-card-premium w-100 py-3 text-center" for="wa_bkk">
                                    <i class="fas fa-city fa-2x mb-2 d-block text-info"></i>กทม. และปริมณฑล
                                </label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="radio" class="role-radio-hidden" name="work_area_type" id="wa_nationwide" value="nationwide" onchange="toggleCustomArea()">
                                <label class="role-card-premium w-100 py-3 text-center" for="wa_nationwide">
                                    <i class="fas fa-globe-asia fa-2x mb-2 d-block text-warning"></i>รับงานทั่วประเทศ
                                </label>
                            </div>
                            <div class="col-md-4 mb-3">
                                <input type="radio" class="role-radio-hidden" name="work_area_type" id="wa_custom" value="custom" onchange="toggleCustomArea()">
                                <label class="role-card-premium w-100 py-3 text-center" for="wa_custom">
                                    <i class="fas fa-map-pin fa-2x mb-2 d-block text-danger"></i>เลือกเฉพาะบางจังหวัด
                                </label>
                            </div>
                        </div>

                        <div id="custom_area_section" class="dynamic-section mb-4" style="display: none;">
                            <label class="form-label text-secondary mb-2">ค้นหาและเลือกจังหวัด (พิมพ์ชื่อจังหวัดได้เลย)</label>
                            <select class="form-select select2-provinces" name="custom_work_areas[]" multiple="multiple" style="width: 100%;">
                                <?php 
                                $thai_provinces = ["กระบี่", "กรุงเทพมหานคร", "กาญจนบุรี", "กาฬสินธุ์", "กำแพงเพชร", "ขอนแก่น", "จันทบุรี", "ฉะเชิงเทรา", "ชลบุรี", "ชัยนาท", "ชัยภูมิ", "ชุมพร", "เชียงราย", "เชียงใหม่", "ตรัง", "ตราด", "ตาก", "นครนายก", "นครปฐม", "นครพนม", "นครราชสีมา", "นครศรีธรรมราช", "นครสวรรค์", "นนทบุรี", "นราธิวาส", "น่าน", "บึงกาฬ", "บุรีรัมย์", "ปทุมธานี", "ประจวบคีรีขันธ์", "ปราจีนบุรี", "ปัตตานี", "พระนครศรีอยุธยา", "พะเยา", "พังงา", "พัทลุง", "พิจิตร", "พิษณุโลก", "เพชรบุรี", "เพชรบูรณ์", "แพร่", "ภูเก็ต", "มหาสารคาม", "มุกดาหาร", "แม่ฮ่องสอน", "ยโสธร", "ยะลา", "ร้อยเอ็ด", "ระนอง", "ระยอง", "ราชบุรี", "ลพบุรี", "ลำปาง", "ลำพูน", "เลย", "ศรีสะเกษ", "สกลนคร", "สงขลา", "สตูล", "สมุทรปราการ", "สมุทรสงคราม", "สมุทรสาคร", "สระแก้ว", "สระบุรี", "สิงห์บุรี", "สุโขทัย", "สุพรรณบุรี", "สุราษฎร์ธานี", "สุรินทร์", "หนองคาย", "หนองบัวลำภู", "อ่างทอง", "อำนาจเจริญ", "อุดรธานี", "อุตรดิตถ์", "อุทัยธานี", "อุบลราชธานี"];
                                foreach ($thai_provinces as $prov): ?>
                                    <option value="<?php echo htmlspecialchars($prov); ?>"><?php echo htmlspecialchars($prov); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- STEP 4: Bio & Availability -->
                    <div class="wizard-step" id="step4">
                        <h4 class="text-white fw-bold mb-4"><i class="fas fa-calendar-alt text-primary me-2"></i> ประวัติ & คิวงาน</h4>
                        
                        <div class="mb-4">
                            <label class="form-label text-secondary">แนะนำตัวเองสั้นๆ (Bio)</label>
                            <textarea class="form-control" name="bio" rows="4" placeholder="บอกเล่าประสบการณ์และสไตล์การเล่นของคุณให้ผู้ว่าจ้างฟัง..." required></textarea>
                        </div>
                        
                        <label class="form-label text-secondary mb-3">ตั้งค่าคิวงานเบื้องต้น (สามารถแก้ภายหลังได้)</label>
                        <div class="card bg-dark border-secondary p-3 mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                                <h6 class="mb-0 text-white fw-bold" id="currentMonthLabel">Month Year</h6>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
                            </div>
                            
                            <div class="neon-calendar-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; text-align: center; color: var(--primary-color); font-weight: bold; font-size: 0.9rem; margin-bottom: 5px;">
                                <div>อา</div><div>จ</div><div>อ</div><div>พ</div><div>พฤ</div><div>ศ</div><div>ส</div>
                            </div>
                            
                            <div class="neon-calendar-grid" id="calendarGrid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; text-align: center;">
                                <!-- Calendar dynamically rendered here -->
                            </div>
                            <input type="hidden" name="availability_calendar_json" id="availability_calendar_json" value="[]">
                        </div>
                    </div>

                    <!-- Wizard Controls -->
                    <div class="wizard-footer">
                        <button type="button" class="btn btn-outline-secondary px-4" onclick="prevStepHook()" id="prevBtn" style="display: none;"><i class="fas fa-arrow-left me-2"></i> ย้อนกลับ</button>
                        <div class="ms-auto">
                            <button type="button" class="btn btn-primary px-5 glow-btn rounded-pill" onclick="nextStepHook()" id="nextBtn">ถัดไป <i class="fas fa-arrow-right ms-2"></i></button>
                            <button type="submit" class="btn btn-success px-5 rounded-pill glow-btn" id="submitBtn" style="display: none;"><i class="fas fa-check me-2"></i> ยืนยันการสมัคร</button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

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

    // Local script to hook up wizard button visibility with the global nextStep/prevStep functions
    function updateButtons() {
        document.getElementById('prevBtn').style.display = currentStep > 1 ? 'inline-block' : 'none';
        if (currentStep === totalSteps) {
            document.getElementById('nextBtn').style.display = 'none';
            document.getElementById('submitBtn').style.display = 'inline-block';
            if (typeof renderCalendar === 'function') {
                renderCalendar();
            }
        } else {
            document.getElementById('nextBtn').style.display = 'inline-block';
            document.getElementById('submitBtn').style.display = 'none';
        }
    }
    
    function nextStepHook() {
        // nextStep() is in script.js
        if(typeof nextStep === 'function') {
            nextStep();
            updateButtons();
        }
    }
    
    function prevStepHook() {
        // prevStep() is in script.js
        if(typeof prevStep === 'function') {
            prevStep();
            updateButtons();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Trigger initial state
        updateButtons();
        toggleCustomArea();
        
        // Form submit handler to prevent empty JSON errors
        document.getElementById('musicianForm').addEventListener('submit', function(e) {
            const calInput = document.getElementById('availability_calendar_json');
            if(!calInput.value) calInput.value = '[]';
        });
    });

    // Initialize Select2 after jQuery is ready (which is at the bottom, so use window.onload or defer)
    window.addEventListener('load', function() {
        if(window.jQuery) {
            $('.select2-provinces').select2({
                placeholder: "ค้นหาจังหวัด...",
                allowClear: true,
                width: '100%'
            });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
