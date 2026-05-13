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
                $success = "สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ";
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "เกิดข้อผิดพลาดในการลงทะเบียน: " . $e->getMessage();
            }
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4 text-primary fw-bold">สมัครสมาชิก</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="register.php">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">ชื่อจริง (First Name)</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">นามสกุล (Last Name)</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">ชื่อผู้ใช้งาน (Username)</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">อีเมล (Email)</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">เบอร์โทรศัพท์ (Phone Number)</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">รหัสผ่าน</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">ยืนยันรหัสผ่าน</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">ลงทะเบียน</button>
                        </div>
                    </form>
                    <div class="text-center mt-3">
                        <p>มีบัญชีอยู่แล้ว? <a href="login.php" class="text-decoration-none">เข้าสู่ระบบ</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
