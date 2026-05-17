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

    // Process Work Areas (Combining Bangkok & Provinces)
    $work_areas = $_POST['work_areas'] ?? [];
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
            $new_filename = uniqid() . '.' . $ext;
            $upload_path = 'uploads/avatars/' . $new_filename;
            if (!is_dir('uploads/avatars')) {
                mkdir('uploads/avatars', 0777, true);
            }
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                $profile_image = $new_filename;
                $_SESSION['profile_image'] = $profile_image;
            }
        }
    }

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

        // Delete Employer Profile just in case (optional, keeping it is fine too, but we switch role)
        // $stmt = $conn->prepare("DELETE FROM employer_profiles WHERE user_id = ?");
        // $stmt->execute([$user_id]);

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
?>
<?php include 'includes/header.php'; ?>



<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="text-center mb-5">
                <h1 class="display-4 fw-bold text-white mb-3">ยกระดับเป็นนักดนตรี 🎸</h1>
                <p class="lead text-secondary">กรอกข้อมูลโปรไฟล์ศิลปินของคุณเพื่อเริ่มรับงานจากผู้ว่าจ้างทันที</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="musician-form-container">
                <form action="become_musician.php" method="POST" enctype="multipart/form-data" id="musicianForm">
                    
                    <!-- 1. รูปโปรไฟล์ -->
                    <h4 class="section-title fw-bold mt-0"><i class="fas fa-camera me-2"></i>1. รูปโปรไฟล์</h4>
                    <div class="mb-4">
                        <input class="form-control bg-dark text-white border-secondary" type="file" id="profile_image" name="profile_image" accept="image/*" required>
                        <small class="text-muted mt-2 d-block">รองรับไฟล์ JPG, PNG, WEBP</small>
                    </div>

                    <!-- 2. ประเภทศิลปิน -->
                    <h4 class="section-title fw-bold"><i class="fas fa-users me-2"></i>2. ประเภทศิลปิน</h4>
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <input type="radio" class="role-radio-hidden" name="band_type" id="type_solo" value="solo" autocomplete="off" checked onchange="toggleBandFields()">
                            <label class="role-card-premium w-100 text-center py-4 px-3 h-100 d-flex flex-column justify-content-center cursor-pointer" for="type_solo">
                                <i class="fas fa-user fa-3x mb-3 text-info"></i>
                                <span class="role-title d-block text-white fw-bold mb-1">ศิลปินเดี่ยว</span>
                                <span class="role-desc d-block text-secondary small">ร้องเดี่ยว, เล่นเครื่องดนตรีชิ้นเดียว</span>
                            </label>
                        </div>
                        <div class="col-md-4 mb-3">
                            <input type="radio" class="role-radio-hidden" name="band_type" id="type_duo" value="duo" autocomplete="off" onchange="toggleBandFields()">
                            <label class="role-card-premium w-100 text-center py-4 px-3 h-100 d-flex flex-column justify-content-center cursor-pointer" for="type_duo">
                                <i class="fas fa-user-friends fa-3x mb-3 text-warning"></i>
                                <span class="role-title d-block text-white fw-bold mb-1">ศิลปินคู่ (Duo)</span>
                                <span class="role-desc d-block text-secondary small">นักร้องดูโอ้, อคูสติกแพ็คคู่</span>
                            </label>
                        </div>
                        <div class="col-md-4 mb-3">
                            <input type="radio" class="role-radio-hidden" name="band_type" id="type_band" value="band" autocomplete="off" onchange="toggleBandFields()">
                            <label class="role-card-premium w-100 text-center py-4 px-3 h-100 d-flex flex-column justify-content-center cursor-pointer" for="type_band">
                                <i class="fas fa-users fa-3x mb-3 text-danger"></i>
                                <span class="role-title d-block text-white fw-bold mb-1">วงดนตรี (Band)</span>
                                <span class="role-desc d-block text-secondary small">ฟูลแบนด์ 3 คนขึ้นไป</span>
                            </label>
                        </div>
                    </div>

                    <!-- Solo Fields -->
                    <div id="solo_fields" class="dynamic-section" style="display: block;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white">อายุ</label>
                                <input type="number" class="form-control bg-dark border-secondary text-white" name="solo_age" min="1" placeholder="เช่น 25">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white">เครื่องดนตรีที่ถนัด</label>
                                <input type="text" class="form-control bg-dark border-secondary text-white" name="solo_instruments" placeholder="เช่น กีตาร์, ร้องนำ, เปียโน">
                            </div>
                        </div>
                    </div>

                    <!-- Duo/Band Fields -->
                    <div id="band_fields" class="dynamic-section">
                        <div class="mb-4">
                            <label class="form-label text-white">ชื่อวงดนตรี</label>
                            <input type="text" class="form-control bg-dark border-secondary text-white" name="band_name" placeholder="ชื่อวงของคุณ">
                        </div>
                        
                        <label class="form-label text-white d-flex justify-content-between">
                            <span>สมาชิกวง</span>
                            <button type="button" class="btn btn-sm btn-outline-success rounded-pill" onclick="addMemberField()"><i class="fas fa-plus me-1"></i>เพิ่มสมาชิก</button>
                        </label>
                        <div id="members_container">
                            <div class="row mb-3 member-row">
                                <div class="col-md-4"><input type="text" class="form-control bg-dark border-secondary text-white" name="member_name[]" placeholder="ชื่อ-นามสกุล"></div>
                                <div class="col-md-3"><input type="number" class="form-control bg-dark border-secondary text-white" name="member_age[]" placeholder="อายุ" min="1"></div>
                                <div class="col-md-4"><input type="text" class="form-control bg-dark border-secondary text-white" name="member_instruments[]" placeholder="เครื่องดนตรี"></div>
                                <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-trash"></i></button></div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. แนวเพลง -->
                    <h4 class="section-title fw-bold"><i class="fas fa-music me-2"></i>3. แนวเพลงที่ถนัด</h4>
                    <div class="row mb-3 custom-checkbox">
                        <?php 
                        $genresList = ['Pop', 'Rock', 'Jazz', 'Acoustic', 'R&B', 'Hip Hop', 'EDM', 'ลูกทุ่ง/เพื่อชีวิต', 'Classic'];
                        foreach ($genresList as $g): ?>
                            <div class="col-md-3 col-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input bg-dark border-secondary" type="checkbox" name="genres[]" value="<?php echo htmlspecialchars($g); ?>" id="genre_<?php echo md5($g); ?>">
                                    <label class="form-check-label text-light" for="genre_<?php echo md5($g); ?>"><?php echo htmlspecialchars($g); ?></label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-white">แนวเพลงอื่นๆ (พิมพ์เพิ่มได้เลย)</label>
                        <input type="text" class="form-control bg-dark border-secondary text-white" name="other_genre" placeholder="ระบุแนวเพลงอื่นๆ...">
                    </div>

                    <!-- 4. เรทค่าจ้าง -->
                    <h4 class="section-title fw-bold"><i class="fas fa-money-bill-wave me-2"></i>4. เรทค่าจ้าง</h4>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-white">ราคา (บาท)</label>
                            <input type="number" class="form-control bg-dark border-secondary text-white" name="rate_amount" placeholder="ระบุราคาตัวเลข" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-white">คิดราคาเป็น</label>
                            <select class="form-select bg-dark border-secondary text-white" name="pricing_type">
                                <option value="hour">รายชั่วโมง</option>
                                <option value="day">รายวัน (หรืองานเหมา)</option>
                            </select>
                        </div>
                    </div>

                    <!-- 5. ภาคที่รับงาน (ระดับภาค) -->
                    <h4 class="section-title fw-bold"><i class="fas fa-map-marker-alt me-2"></i>5. ภาคที่รับงาน</h4>
                    
                    <div class="card bg-dark border-secondary mb-4 p-4">
                        <div class="row g-3">
                            <?php
                            $regions_mapping = [
                                'ภาคกลาง' => ['icon' => 'fa-city', 'desc' => 'กรุงเทพมหานคร และปริมณฑล', 'color' => 'text-info'],
                                'ภาคเหนือ' => ['icon' => 'fa-mountain', 'desc' => 'เชียงใหม่, เชียงราย, แม่ฮ่องสอน ฯลฯ', 'color' => 'text-success'],
                                'ภาคตะวันออกเฉียงเหนือ' => ['icon' => 'fa-sun', 'desc' => 'ขอนแก่น, โคราช, อุดรธานี ฯลฯ', 'color' => 'text-warning'],
                                'ภาคใต้' => ['icon' => 'fa-umbrella-beach', 'desc' => 'ภูเก็ต, หาดใหญ่, สุราษฎร์ธานี ฯลฯ', 'color' => 'text-primary'],
                                'ภาคตะวันออก' => ['icon' => 'fa-water', 'desc' => 'ชลบุรี, พัทยา, ระยอง ฯลฯ', 'color' => 'text-danger'],
                                'ภาคตะวันตก' => ['icon' => 'fa-tree', 'desc' => 'กาญจนบุรี, ตาก, ราชบุรี ฯลฯ', 'color' => 'text-pink']
                            ];

                            foreach ($regions_mapping as $reg_name => $reg_info):
                                $reg_id = 'reg_' . md5($reg_name);
                                ?>
                                <div class="col-md-4 col-sm-6">
                                    <input type="checkbox" class="role-radio-hidden" name="work_areas[]" value="<?php echo htmlspecialchars($reg_name); ?>" id="<?php echo $reg_id; ?>">
                                    <label class="role-card-premium w-100 text-center py-4 px-3 h-100 d-flex flex-column justify-content-center cursor-pointer" for="<?php echo $reg_id; ?>">
                                        <i class="fas <?php echo $reg_info['icon']; ?> fa-2x mb-3 <?php echo $reg_info['color']; ?>"></i>
                                        <span class="role-title d-block text-white fw-bold mb-1" style="font-size: 1.05rem;"><?php echo $reg_name; ?></span>
                                        <span class="role-desc d-block text-secondary small" style="font-size: 0.78rem;"><?php echo $reg_info['desc']; ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 6. วันเวลาที่รับงาน -->
                    <h4 class="section-title fw-bold"><i class="fas fa-calendar-alt me-2"></i>6. วันเวลาที่รับงาน (ปฏิทินปฏิทิน)</h4>
                    <p class="text-secondary small mb-4">เลือกวันที่คุณพร้อมทำการแสดงโดยคลิกที่ปฏิทิน แล้วกำหนดช่วงเวลาว่างที่สามารถรับงานได้</p>

                    <div class="row mb-4">
                        <!-- Left: Calendar Widget -->
                        <div class="col-lg-7 mb-4 mb-lg-0">
                            <div class="neon-calendar-container">
                                <div class="neon-calendar-header">
                                    <button type="button" class="neon-calendar-btn" onclick="prevMonth()"><i class="fas fa-chevron-left"></i></button>
                                    <div class="neon-calendar-title" id="calendar_title">พฤษภาคม 2569</div>
                                    <button type="button" class="neon-calendar-btn" onclick="nextMonth()"><i class="fas fa-chevron-right"></i></button>
                                </div>
                                <div class="neon-calendar-weekdays">
                                    <div>อา</div><div>จ</div><div>อ</div><div>พ</div><div>พฤ</div><div>ศ</div><div>ส</div>
                                </div>
                                <div class="neon-calendar-grid" id="calendar_grid">
                                    <!-- Days dynamically rendered via Javascript -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right: Time Editor Panel -->
                        <div class="col-lg-5">
                            <div class="neon-time-panel h-100 d-flex flex-column justify-content-center p-4 border border-secondary border-opacity-15 rounded-4 bg-dark bg-opacity-20">
                                <div id="time_editor_empty" class="text-center py-5 text-secondary">
                                    <i class="fas fa-hand-pointer fa-3x mb-3 text-cyan opacity-50 animate__pulse"></i>
                                    <h6 class="text-light fw-bold">กรุณาคลิกเลือกวันที่บนปฏิทิน</h6>
                                    <p class="small mb-0">เพื่อกำหนดคิวรับงานในวันนั้น</p>
                                </div>
                                
                                <div id="time_editor_form" style="display: none;" class="animate__animated animate__fadeIn text-center py-3">
                                    <h5 class="text-white fw-bold mb-4 border-bottom border-secondary border-opacity-15 pb-3" id="editing_date_label">
                                        <i class="fas fa-calendar-alt text-pink me-2"></i>ตั้งค่าสำหรับวันที่...
                                    </h5>
                                    
                                    <div class="form-check form-switch custom-switch-premium d-inline-block mb-3">
                                        <input class="form-check-input cursor-pointer" type="checkbox" id="date_active_toggle" onchange="toggleDateActive(this)">
                                        <label class="form-check-label text-light fw-bold cursor-pointer" for="date_active_toggle" style="font-size: 1.1rem; padding-left: 0.5rem;">
                                            เปิดรับงานในวันนี้
                                        </label>
                                    </div>
                                    
                                    <!-- Hidden elements to keep absolute compatibility with JS states without errors -->
                                    <div id="time_inputs_wrapper" style="display: none;">
                                        <input type="hidden" id="date_start_time" value="18:00">
                                        <input type="hidden" id="date_end_time" value="21:00">
                                        <select id="start_hour" style="display: none;"><option value="18" selected>18</option></select>
                                        <select id="start_minute" style="display: none;"><option value="00" selected>00</option></select>
                                        <select id="end_hour" style="display: none;"><option value="21" selected>21</option></select>
                                        <select id="end_minute" style="display: none;"><option value="00" selected>00</option></select>
                                    </div>
                                    
                                    <div class="text-secondary small mt-4 border-top border-secondary border-opacity-10 pt-3 text-start">
                                        * เมื่อเลือกเปิดรับงาน วันดังกล่าวจะเปลี่ยนเป็นแถบสีเรืองแสงทันที คุณสามารถบันทึกเพื่ออัปเดตลงฐานข้อมูลเมื่อกดปุ่ม "ยืนยันการสมัคร" ด้านล่างสุด
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden input to store actual calendar slots JSON -->
                    <input type="hidden" name="availability_calendar_json" id="availability_calendar_json" value="[]">

                    <!-- 7. แนะนำตัว -->
                    <h4 class="section-title fw-bold"><i class="fas fa-id-card me-2"></i>7. แนะนำตัว / ประวัติ</h4>
                    <div class="mb-5">
                        <textarea class="form-control bg-dark border-secondary text-white" name="bio" rows="5" placeholder="เขียนประวัติย่อๆ ประสบการณ์การทำงาน จุดเด่นของวง..." required></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-bold py-3 fs-5 shadow-lg">ยืนยันการสมัครเป็นนักดนตรี และไปเพิ่มผลงาน</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle showing solo or band fields based on selected artist type
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

    // Add a band member row dynamically
    function addMemberField() {
        const container = document.getElementById('members_container');
        const row = document.createElement('div');
        row.className = 'row mb-3 member-row align-items-end animate__animated animate__fadeInUp';
        row.innerHTML = `
        <div class="col-md-4">
            <label class="text-secondary small mb-1">ชื่อสมาชิก</label>
            <input type="text" class="form-control bg-dark border-secondary text-white" name="member_name[]" placeholder="ระบุชื่อ" required>
        </div>
        <div class="col-md-3">
            <label class="text-secondary small mb-1">อายุ</label>
            <input type="number" class="form-control bg-dark border-secondary text-white" name="member_age[]" placeholder="ระบุอายุ" required>
        </div>
        <div class="col-md-4">
            <label class="text-secondary small mb-1">เครื่องดนตรี / หน้าที่</label>
            <input type="text" class="form-control bg-dark border-secondary text-white" name="member_instruments[]" placeholder="เช่น นักร้องนำ, กีตาร์" required>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger w-100 py-2.5" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-trash"></i></button>
        </div>
    `;
        container.appendChild(row);
    }

    // NEON CALENDAR STATE ENGINE
    let calendarData = []; // Array of {date: "YYYY-MM-DD", start: "HH:MM", end: "HH:MM"}
    let currentYear, currentMonth;
    let selectedDateStr = null;

    const monthNamesTH = [
        "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
        "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
    ];

    // Render calendar cells dynamically
    function renderCalendar() {
        const grid = document.getElementById('calendar_grid');
        if (!grid) return;
        grid.innerHTML = '';

        // Month Year Header Title (Buddhist Era conversion +543)
        const titleText = `${monthNamesTH[currentMonth]} ${currentYear + 543}`;
        document.getElementById('calendar_title').innerText = titleText;

        const firstDayIndex = new Date(currentYear, currentMonth, 1).getDay();
        const totalDays = new Date(currentYear, currentMonth + 1, 0).getDate();

        // Pin the navigation within the current year only
        const prevBtn = document.querySelector('button[onclick="prevMonth()"]');
        const nextBtn = document.querySelector('button[onclick="nextMonth()"]');
        if (prevBtn) {
            prevBtn.style.opacity = (currentMonth <= 0) ? '0.3' : '1';
            prevBtn.style.pointerEvents = (currentMonth <= 0) ? 'none' : 'auto';
        }
        if (nextBtn) {
            nextBtn.style.opacity = (currentMonth >= 11) ? '0.3' : '1';
            nextBtn.style.pointerEvents = (currentMonth >= 11) ? 'none' : 'auto';
        }

        // Pad blank days of previous month
        for (let i = 0; i < firstDayIndex; i++) {
            const inactiveDiv = document.createElement('div');
            inactiveDiv.className = 'neon-calendar-day inactive';
            grid.appendChild(inactiveDiv);
        }

        // Render current month days
        for (let day = 1; day <= totalDays; day++) {
            const dayDiv = document.createElement('div');
            dayDiv.className = 'neon-calendar-day';
            dayDiv.innerText = day;

            const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            dayDiv.setAttribute('data-date', dateStr);

            // Active availability slot highlighting
            const slot = calendarData.find(item => item.date === dateStr);
            if (slot) {
                dayDiv.classList.add('active-slot');
            }

            // Current select state highlighting
            if (selectedDateStr === dateStr) {
                dayDiv.classList.add('selected');
            }

            dayDiv.addEventListener('click', () => selectDate(dateStr, dayDiv));
            grid.appendChild(dayDiv);
        }
    }

    function prevMonth() {
        if (currentMonth <= 0) return;
        currentMonth--;
        renderCalendar();
    }

    function nextMonth() {
        if (currentMonth >= 11) return;
        currentMonth++;
        renderCalendar();
    }

    function combineStartTime() {
        const h = document.getElementById('start_hour').value;
        const m = document.getElementById('start_minute').value;
        document.getElementById('date_start_time').value = `${h}:${m}`;
        updateDateData();
    }

    function combineEndTime() {
        const h = document.getElementById('end_hour').value;
        const m = document.getElementById('end_minute').value;
        document.getElementById('date_end_time').value = `${h}:${m}`;
        updateDateData();
    }

    function selectDate(dateStr, dayElement) {
        selectedDateStr = dateStr;

        // Toggle selected styling
        document.querySelectorAll('.neon-calendar-day').forEach(el => el.classList.remove('selected'));
        dayElement.classList.add('selected');

        // Show configuration panel
        document.getElementById('time_editor_empty').style.display = 'none';
        document.getElementById('time_editor_form').style.display = 'block';

        const parts = dateStr.split('-');
        const y = parseInt(parts[0]) + 543;
        const m = monthNamesTH[parseInt(parts[1]) - 1];
        const d = parseInt(parts[2]);
        document.getElementById('editing_date_label').innerHTML = `<i class="fas fa-edit text-pink me-2"></i>ตั้งเวลาสำหรับวันที่ ${d} ${m} ${y}`;

        // Populate panel inputs with saved slot details
        const slot = calendarData.find(item => item.date === dateStr);
        const toggle = document.getElementById('date_active_toggle');
        const wrapper = document.getElementById('time_inputs_wrapper');
        const startInput = document.getElementById('date_start_time');
        const endInput = document.getElementById('date_end_time');

        if (slot) {
            toggle.checked = true;
            wrapper.style.display = 'block';
            
            const startVal = slot.start || '18:00';
            const endVal = slot.end || '21:00';
            
            startInput.value = startVal;
            endInput.value = endVal;
            
            const startParts = startVal.split(':');
            document.getElementById('start_hour').value = startParts[0] || '18';
            document.getElementById('start_minute').value = startParts[1] || '00';
            
            const endParts = endVal.split(':');
            document.getElementById('end_hour').value = endParts[0] || '21';
            document.getElementById('end_minute').value = endParts[1] || '00';
        } else {
            toggle.checked = false;
            wrapper.style.display = 'none';
            startInput.value = '';
            endInput.value = '';
            
            document.getElementById('start_hour').value = '18';
            document.getElementById('start_minute').value = '00';
            document.getElementById('end_hour').value = '21';
            document.getElementById('end_minute').value = '00';
        }
    }

    function toggleDateActive(checkbox) {
        const wrapper = document.getElementById('time_inputs_wrapper');
        const startInput = document.getElementById('date_start_time');
        const endInput = document.getElementById('date_end_time');

        if (checkbox.checked) {
            wrapper.style.display = 'block';
            wrapper.classList.add('animate__animated', 'animate__fadeIn');
            if (!startInput.value) startInput.value = '18:00';
            if (!endInput.value) endInput.value = '21:00';
            
            const startParts = startInput.value.split(':');
            document.getElementById('start_hour').value = startParts[0] || '18';
            document.getElementById('start_minute').value = startParts[1] || '00';
            
            const endParts = endInput.value.split(':');
            document.getElementById('end_hour').value = endParts[0] || '21';
            document.getElementById('end_minute').value = endParts[1] || '00';
            
            updateDateData();
        } else {
            wrapper.style.display = 'none';
            calendarData = calendarData.filter(item => item.date !== selectedDateStr);
            saveCalendarJSON();
            updateCalendarDayCell(selectedDateStr, false);
        }
    }

    function updateDateData() {
        const startVal = document.getElementById('date_start_time').value;
        const endVal = document.getElementById('date_end_time').value;

        let slot = calendarData.find(item => item.date === selectedDateStr);
        if (slot) {
            slot.start = startVal;
            slot.end = endVal;
        } else {
            calendarData.push({
                date: selectedDateStr,
                start: startVal,
                end: endVal
            });
        }

        saveCalendarJSON();
        updateCalendarDayCell(selectedDateStr, true);
    }

    function updateCalendarDayCell(dateStr, isActive) {
        const cell = document.querySelector(`.neon-calendar-day[data-date="${dateStr}"]`);
        if (cell) {
            if (isActive) {
                cell.classList.add('active-slot');
            } else {
                cell.classList.remove('active-slot');
            }
        }
    }

    function saveCalendarJSON() {
        document.getElementById('availability_calendar_json').value = JSON.stringify(calendarData, null, 2);
    }

    // Initialize Page
    document.addEventListener('DOMContentLoaded', () => {
        toggleBandFields();
        
        // Parse calendar JSON values
        const jsonVal = document.getElementById('availability_calendar_json').value;
        try {
            const parsed = JSON.parse(jsonVal);
            if (Array.isArray(parsed)) {
                // Ensure backward compatibility or format correction
                calendarData = parsed.filter(item => item && item.date);
            }
        } catch(e) {
            calendarData = [];
        }

        const today = new Date();
        currentYear = today.getFullYear();
        currentMonth = today.getMonth();
        renderCalendar();
    });
</script>

<?php include 'includes/footer.php'; ?>
