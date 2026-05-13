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
    
    // Process Genres
    $genres = $_POST['genres'] ?? [];
    if (!empty(trim($_POST['other_genre']))) {
        $genres[] = trim($_POST['other_genre']);
    }
    $genres_str = implode(', ', $genres);

    // Process Work Areas (Combining Bangkok & Provinces)
    $work_areas = $_POST['work_areas'] ?? [];
    $work_areas_json = json_encode($work_areas, JSON_UNESCAPED_UNICODE);

    // Process Availability (Days & Specific Times)
    $availability_days = $_POST['availability_days'] ?? [];
    $time_start = $_POST['time_start'] ?? [];
    $time_end = $_POST['time_end'] ?? [];
    
    $availability = [];
    foreach ($availability_days as $day) {
        $availability[$day] = [
            'start' => $time_start[$day] ?? '',
            'end' => $time_end[$day] ?? ''
        ];
    }
    // We store the full array into availability_days to keep schema unchanged
    $days_json = json_encode($availability, JSON_UNESCAPED_UNICODE);
    $times_json = json_encode([], JSON_UNESCAPED_UNICODE); // Kept for backwards schema compatibility

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
        $stmt = $conn->prepare("UPDATE musician_profiles SET 
            bio = ?, band_type = ?, age = ?, instruments = ?, band_members = ?, genres = ?, rate = ?, pricing_type = ?, work_areas = ?, availability_days = ?, availability_times = ?
            WHERE user_id = ?");
        
        $stmt->execute([
            $bio, $band_type, $age, $instruments, $band_members_json, 
            $genres_str, $rate_amount, $pricing_type, $work_areas_json, $days_json, $times_json, $user_id
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

// Decode JSONs for form population
$selected_genres = array_map('trim', explode(',', $profile['genres']));
$selected_areas = json_decode($profile['work_areas'], true) ?? [];
$selected_days = json_decode($profile['availability_days'], true) ?? [];
$selected_times = json_decode($profile['availability_times'], true) ?? [];
$band_data = json_decode($profile['band_members'], true) ?? ['band_name' => '', 'members' => []];
?>
<?php include 'includes/header.php'; ?>



<div class="container py-5 mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-white mb-3"><i class="fas fa-guitar text-primary me-2"></i>แก้ไขข้อมูลศิลปินของคุณ</h2>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="musician-form-container">
                <form action="edit_musician.php" method="POST" id="musicianForm">
                    
                    <!-- 2. ประเภทศิลปิน -->
                    <h4 class="section-title fw-bold mt-0"><i class="fas fa-users me-2"></i>ประเภทศิลปิน</h4>
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <input type="radio" class="btn-check" name="band_type" id="type_solo" value="solo" <?php echo ($profile['band_type'] == 'solo' ? 'checked' : ''); ?> onchange="toggleBandFields()">
                            <label class="btn btn-outline-primary w-100 py-3" for="type_solo">
                                <i class="fas fa-user fa-2x mb-2 d-block"></i>ศิลปินเดี่ยว
                            </label>
                        </div>
                        <div class="col-md-4 mb-3">
                            <input type="radio" class="btn-check" name="band_type" id="type_duo" value="duo" <?php echo ($profile['band_type'] == 'duo' ? 'checked' : ''); ?> onchange="toggleBandFields()">
                            <label class="btn btn-outline-primary w-100 py-3" for="type_duo">
                                <i class="fas fa-user-friends fa-2x mb-2 d-block"></i>ศิลปินคู่
                            </label>
                        </div>
                        <div class="col-md-4 mb-3">
                            <input type="radio" class="btn-check" name="band_type" id="type_band" value="band" <?php echo ($profile['band_type'] == 'band' ? 'checked' : ''); ?> onchange="toggleBandFields()">
                            <label class="btn btn-outline-primary w-100 py-3" for="type_band">
                                <i class="fas fa-users fa-2x mb-2 d-block"></i>วงดนตรี (3 คนขึ้นไป)
                            </label>
                        </div>
                    </div>

                    <!-- Solo Fields -->
                    <div id="solo_fields" class="dynamic-section" style="display: <?php echo ($profile['band_type'] == 'solo' ? 'block' : 'none'); ?>;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white">อายุ</label>
                                <input type="number" class="form-control bg-dark border-secondary text-white" name="solo_age" min="1" value="<?php echo htmlspecialchars($profile['age'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white">เครื่องดนตรีที่ถนัด</label>
                                <input type="text" class="form-control bg-dark border-secondary text-white" name="solo_instruments" value="<?php echo htmlspecialchars($profile['instruments'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Duo/Band Fields -->
                    <div id="band_fields" class="dynamic-section" style="display: <?php echo ($profile['band_type'] != 'solo' ? 'block' : 'none'); ?>;">
                        <div class="mb-4">
                            <label class="form-label text-white">ชื่อวงดนตรี</label>
                            <input type="text" class="form-control bg-dark border-secondary text-white" name="band_name" value="<?php echo htmlspecialchars($band_data['band_name'] ?? ''); ?>">
                        </div>
                        
                        <label class="form-label text-white d-flex justify-content-between">
                            <span>สมาชิกวง</span>
                            <button type="button" class="btn btn-sm btn-outline-success rounded-pill" onclick="addMemberField()"><i class="fas fa-plus me-1"></i>เพิ่มสมาชิก</button>
                        </label>
                        <div id="members_container">
                            <?php 
                            if (!empty($band_data['members'])):
                                foreach ($band_data['members'] as $m): ?>
                                <div class="row mb-3 member-row">
                                    <div class="col-md-4"><input type="text" class="form-control bg-dark border-secondary text-white" name="member_name[]" value="<?php echo htmlspecialchars($m['name']); ?>"></div>
                                    <div class="col-md-3"><input type="number" class="form-control bg-dark border-secondary text-white" name="member_age[]" value="<?php echo htmlspecialchars($m['age']); ?>"></div>
                                    <div class="col-md-4"><input type="text" class="form-control bg-dark border-secondary text-white" name="member_instruments[]" value="<?php echo htmlspecialchars($m['instrument']); ?>"></div>
                                    <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-trash"></i></button></div>
                                </div>
                                <?php endforeach;
                            else: ?>
                                <div class="row mb-3 member-row">
                                    <div class="col-md-4"><input type="text" class="form-control bg-dark border-secondary text-white" name="member_name[]" placeholder="ชื่อ-นามสกุล"></div>
                                    <div class="col-md-3"><input type="number" class="form-control bg-dark border-secondary text-white" name="member_age[]" placeholder="อายุ" min="1"></div>
                                    <div class="col-md-4"><input type="text" class="form-control bg-dark border-secondary text-white" name="member_instruments[]" placeholder="เครื่องดนตรี"></div>
                                    <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-trash"></i></button></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 3. แนวเพลง -->
                    <h4 class="section-title fw-bold"><i class="fas fa-music me-2"></i>แนวเพลงที่ถนัด</h4>
                    <div class="row mb-3 custom-checkbox">
                        <?php 
                        $genresList = ['Pop', 'Rock', 'Jazz', 'Acoustic', 'R&B', 'Hip Hop', 'EDM', 'ลูกทุ่ง/เพื่อชีวิต', 'Classic'];
                        $custom_genre = '';
                        foreach ($genresList as $g): 
                            $checked = in_array($g, $selected_genres) ? 'checked' : '';
                        ?>
                            <div class="col-md-3 col-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input bg-dark border-secondary" type="checkbox" name="genres[]" value="<?php echo htmlspecialchars($g); ?>" id="genre_<?php echo md5($g); ?>" <?php echo $checked; ?>>
                                    <label class="form-check-label text-light" for="genre_<?php echo md5($g); ?>"><?php echo htmlspecialchars($g); ?></label>
                                </div>
                            </div>
                        <?php endforeach; 
                        
                        // Find custom genres
                        $custom_genres = array_diff($selected_genres, $genresList);
                        $custom_genre_str = implode(', ', $custom_genres);
                        ?>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-white">แนวเพลงอื่นๆ</label>
                        <input type="text" class="form-control bg-dark border-secondary text-white" name="other_genre" value="<?php echo htmlspecialchars($custom_genre_str); ?>">
                    </div>

                    <!-- 4. เรทค่าจ้าง -->
                    <h4 class="section-title fw-bold"><i class="fas fa-money-bill-wave me-2"></i>เรทค่าจ้าง</h4>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-white">ราคา (บาท)</label>
                            <input type="number" class="form-control bg-dark border-secondary text-white" name="rate_amount" value="<?php echo htmlspecialchars($profile['rate'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-white">คิดราคาเป็น</label>
                            <select class="form-select bg-dark border-secondary text-white" name="pricing_type">
                                <option value="hour" <?php echo ($profile['pricing_type'] == 'hour' ? 'selected' : ''); ?>>รายชั่วโมง</option>
                                <option value="day" <?php echo ($profile['pricing_type'] == 'day' ? 'selected' : ''); ?>>รายวัน (หรืองานเหมา)</option>
                            </select>
                        </div>
                    </div>

                    <!-- 5. เขตพื้นที่รับงาน -->
                    <h4 class="section-title fw-bold"><i class="fas fa-map-marker-alt me-2"></i>เขตพื้นที่รับงาน <span class="text-muted fs-6 fw-normal">(ระบุได้อย่างละเอียด)</span></h4>
                    <div class="card bg-dark border-secondary mb-4">
                        <div class="card-body">
                            <h5 class="text-info fw-bold mb-3">กรุงเทพมหานคร (ทุกเขต)</h5>
                            <div class="checkbox-grid custom-checkbox">
                                <?php 
                                $bkk_zones = ['พระนคร', 'ดุสิต', 'หนองจอก', 'บางรัก', 'บางเขน', 'บางกะปิ', 'ปทุมวัน', 'ป้อมปราบศัตรูพ่าย', 'พระโขนง', 'มีนบุรี', 'ลาดกระบัง', 'ยานนาวา', 'สัมพันธวงศ์', 'พญาไท', 'ธนบุรี', 'บางกอกใหญ่', 'ห้วยขวาง', 'คลองสาน', 'ตลิ่งชัน', 'บางกอกน้อย', 'บางขุนเทียน', 'ภาษีเจริญ', 'หนองแขม', 'ราษฎร์บูรณะ', 'บางพลัด', 'ดินแดง', 'บึงกุ่ม', 'สาทร', 'บางซื่อ', 'จตุจักร', 'บางคอแหลม', 'ประเวศ', 'คลองเตย', 'สวนหลวง', 'จอมทอง', 'ดอนเมือง', 'ราชเทวี', 'ลาดพร้าว', 'วัฒนา', 'บางแค', 'หลักสี่', 'สายไหม', 'คันนายาว', 'สะพานสูง', 'วังทองหลาง', 'คลองสามวา', 'บางนา', 'ทวีวัฒนา', 'ทุ่งครุ', 'บางบอน'];
                                foreach ($bkk_zones as $zone): 
                                    $bkk_val = "กรุงเทพมหานคร > เขต" . $zone;
                                    $checked = in_array($bkk_val, $selected_areas) ? 'checked' : '';
                                ?>
                                    <div class="form-check">
                                        <input class="form-check-input bg-dark border-secondary" type="checkbox" name="work_areas[]" value="<?php echo htmlspecialchars($bkk_val); ?>" id="zone_<?php echo md5($zone); ?>" <?php echo $checked; ?>>
                                        <label class="form-check-label text-light" for="zone_<?php echo md5($zone); ?>"><?php echo htmlspecialchars($zone); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-dark border-secondary mb-4">
                        <div class="card-body">
                            <h5 class="text-warning fw-bold mb-3">ต่างจังหวัด</h5>
                            <p class="text-muted small">เลือกจังหวัด, อำเภอ, ตำบล และกดปุ่ม "เพิ่มพื้นที่"</p>
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <select class="form-select bg-dark border-secondary text-white mb-2" id="prov_select" onchange="onProvinceChange()">
                                        <option value="">-- กำลังโหลดจังหวัด --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select bg-dark border-secondary text-white mb-2" id="amph_select" onchange="onAmphureChange()" disabled>
                                        <option value="">-- เลือกอำเภอ --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select bg-dark border-secondary text-white mb-2" id="tam_select" disabled>
                                        <option value="">-- เลือกตำบล --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-outline-warning w-100" onclick="addProvincialArea()">
                                        <i class="fas fa-plus me-1"></i>เพิ่มพื้นที่
                                    </button>
                                </div>
                            </div>
                            <div id="provincial_areas_container" class="p-2 border border-secondary rounded min-vh-25">
                                <?php 
                                foreach($selected_areas as $area) {
                                    if(strpos($area, 'กรุงเทพมหานคร') === false) { // Skip BKK ones as they are checkboxes
                                        echo '<div class="badge bg-secondary p-2 me-2 mb-2 fs-6 d-inline-flex align-items-center">';
                                        echo '<span>'.htmlspecialchars($area).'</span>';
                                        echo '<input type="hidden" name="work_areas[]" value="'.htmlspecialchars($area).'">';
                                        echo '<button type="button" class="btn-close btn-close-white ms-2" style="font-size: 0.6rem;" onclick="this.parentElement.remove()"></button>';
                                        echo '</div>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- 6. วันเวลาที่รับงาน -->
                    <h4 class="section-title fw-bold"><i class="fas fa-calendar-alt me-2"></i>วันเวลาที่รับงาน <span class="text-muted fs-6 fw-normal">(ระบุเวลาที่ชัดเจน)</span></h4>
                    <div class="row mb-4 custom-checkbox">
                        <?php 
                        $days_mapping = [
                            'Monday' => 'วันจันทร์', 'Tuesday' => 'วันอังคาร', 'Wednesday' => 'วันพุธ', 
                            'Thursday' => 'วันพฤหัสบดี', 'Friday' => 'วันศุกร์', 'Saturday' => 'วันเสาร์', 'Sunday' => 'วันอาทิตย์'
                        ];
                        // Process the structured availability_days JSON which is now an associative array (day => ['start', 'end'])
                        $parsed_days = [];
                        if (is_array($selected_days)) {
                            // Support old format just in case
                            if (isset($selected_days[0]) && is_string($selected_days[0])) {
                                // Old array of strings: do nothing, checkboxes won't be checked
                            } else {
                                $parsed_days = $selected_days;
                            }
                        }

                        foreach ($days_mapping as $en_day => $th_day): 
                            $is_checked = isset($parsed_days[$en_day]);
                            $t_start = $is_checked ? $parsed_days[$en_day]['start'] : '';
                            $t_end = $is_checked ? $parsed_days[$en_day]['end'] : '';
                            $display = $is_checked ? 'flex' : 'none';
                        ?>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-dark border-secondary p-3">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input day-checkbox bg-dark border-secondary" type="checkbox" name="availability_days[]" value="<?php echo $en_day; ?>" id="day_<?php echo $en_day; ?>" onchange="toggleTimeInputs(this)" <?php echo $is_checked ? 'checked' : ''; ?>>
                                        <label class="form-check-label text-light fw-bold" for="day_<?php echo $en_day; ?>"><?php echo $th_day; ?></label>
                                    </div>
                                    <div class="time-inputs row g-2" id="time_inputs_<?php echo $en_day; ?>" style="display: <?php echo $display; ?>;">
                                        <div class="col-6">
                                            <label class="text-muted small">เวลาเริ่ม</label>
                                            <input type="time" class="form-control bg-dark text-white border-secondary" name="time_start[<?php echo $en_day; ?>]" value="<?php echo htmlspecialchars($t_start); ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="text-muted small">เวลาสิ้นสุด</label>
                                            <input type="time" class="form-control bg-dark text-white border-secondary" name="time_end[<?php echo $en_day; ?>]" value="<?php echo htmlspecialchars($t_end); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 7. แนะนำตัว -->
                    <h4 class="section-title fw-bold"><i class="fas fa-id-card me-2"></i>แนะนำตัว / ประวัติ</h4>
                    <div class="mb-5">
                        <textarea class="form-control bg-dark border-secondary text-white" name="bio" rows="5" required><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="profile.php" class="btn btn-outline-light rounded-pill px-4">ยกเลิก</a>
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow-lg">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>



<?php include 'includes/footer.php'; ?>
