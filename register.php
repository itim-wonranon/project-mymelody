<?php
require_once 'includes/db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = 'employer'; // Default to employer for everyone

    if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($phone) || empty($password)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } elseif ($password !== $confirm_password) {
        $error = "รหัสผ่านไม่ตรงกัน";
    } else {
        // Check if username or email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->rowCount() > 0) {
            $error = "ชื่อผู้ใช้งานหรืออีเมลนี้มีอยู่ในระบบแล้ว";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            try {
                $conn->beginTransaction();

                // Insert user
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $role, $first_name, $last_name, $phone]);
                $user_id = $conn->lastInsertId();

                // Create employer profile by default
                $stmt = $conn->prepare("INSERT INTO employer_profiles (user_id) VALUES (?)");
                $stmt->execute([$user_id]);

                $conn->commit();
                $_SESSION['success_msg'] = "สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ";
                header("Location: login.php");
                exit();
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "เกิดข้อผิดพลาดในการลงทะเบียน: " . $e->getMessage();
            }
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

<!-- custom full-screen login container -->
<div class="login-wrapper">
    <div class="login-left">
        <!-- background image via CSS -->
        <div class="login-overlay">
            <h1 class="login-title">JOIN THE<br>COMMUNITY<br><span class="text-primary"
                    style="font-style: italic; letter-spacing: 1px;">MY MELODY</span></h1>
            <p class="login-subtitle mt-3">เริ่มต้นเส้นทางดนตรีของคุณ สมัครสมาชิกเพื่อค้นหาโอกาสสำหรับตัวคุณ</p>
        </div>
    </div>
    <div class="login-right py-4">
        <div class="login-form-container" style="max-width: 500px;">
            <div class="text-center mb-4">
                <div class="mb-3 d-inline-block p-3 rounded-circle"
                    style="background: rgba(196, 113, 237, 0.1); border: 1px solid rgba(196, 113, 237, 0.2);">
                    <i class="fas fa-user-plus text-primary"
                        style="font-size: 2.5rem; filter: drop-shadow(0 0 10px rgba(196, 113, 237, 0.5));"></i>
                </div>
                <h2 class="fw-bold fs-2">สมัครสมาชิก</h2>
                <p class="text-secondary mt-1">กรอกข้อมูลด้านล่างเพื่อสร้างบัญชีใหม่</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 rounded-4" role="alert"
                    style="background: rgba(220,53,69,0.1); color: #ff6b6b; padding: 1rem 1.25rem;">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"
                        aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" class="mt-2">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="first_name" class="form-label text-secondary fw-semibold">ชื่อจริง</label>
                        <div class="input-group login-input-group">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control" id="first_name" name="first_name"
                                placeholder="ชื่อจริง" required>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="last_name" class="form-label text-secondary fw-semibold">นามสกุล</label>
                        <div class="input-group login-input-group">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control" id="last_name" name="last_name"
                                placeholder="นามสกุล" required>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label text-secondary fw-semibold">ชื่อผู้ใช้งาน</label>
                    <div class="input-group login-input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username"
                            placeholder="ตั้งชื่อผู้ใช้งาน" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label text-secondary fw-semibold">อีเมล</label>
                    <div class="input-group login-input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" placeholder="example@email.com"
                            required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label text-secondary fw-semibold">เบอร์โทรศัพท์</label>
                    <div class="input-group login-input-group">
                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="08x-xxx-xxxx"
                            required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label text-secondary fw-semibold">รหัสผ่าน</label>
                        <div class="input-group login-input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password"
                                placeholder="••••••••" required>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label for="confirm_password"
                            class="form-label text-secondary fw-semibold">ยืนยันรหัสผ่าน</label>
                        <div class="input-group login-input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                placeholder="••••••••" required>
                        </div>
                    </div>
                </div>

                <div class="d-grid mb-4">
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill glow-btn fw-bold py-2">ลงทะเบียน <i
                            class="fas fa-arrow-right ms-2"></i></button>
                </div>
            </form>
            <div class="text-center mt-auto">
                <p class="mb-0 text-secondary">มีบัญชีอยู่แล้ว? <a href="login.php"
                        class="text-primary fw-bold text-decoration-none hover-glow ms-1">เข้าสู่ระบบ</a></p>
            </div>
        </div>
    </div>
</div>

<?php
$hide_footer = true;
include 'includes/footer.php';
?>