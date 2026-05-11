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
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role']; // 'musician' or 'employer'

    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } elseif ($password !== $confirm_password) {
        $error = "รหัสผ่านไม่ตรงกัน";
    } elseif (!in_array($role, ['musician', 'employer'])) {
        $error = "ประเภทผู้ใช้งานไม่ถูกต้อง";
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
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $role]);
                $user_id = $conn->lastInsertId();
                
                // Create profile based on role
                if ($role === 'musician') {
                    $stmt = $conn->prepare("INSERT INTO musician_profiles (user_id, band_type) VALUES (?, 'solo')");
                    $stmt->execute([$user_id]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO employer_profiles (user_id) VALUES (?)");
                    $stmt->execute([$user_id]);
                }
                
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
                        <div class="mb-3">
                            <label class="form-label">สมัครในฐานะ</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="role" id="roleMusician" value="musician" checked>
                                    <label class="form-check-label" for="roleMusician">
                                        <i class="fas fa-guitar text-primary"></i> นักดนตรี (รับงาน)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="role" id="roleEmployer" value="employer">
                                    <label class="form-check-label" for="roleEmployer">
                                        <i class="fas fa-briefcase text-primary"></i> ผู้ว่าจ้าง (หานักดนตรี)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">ชื่อผู้ใช้งาน (Username)</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">อีเมล</label>
                            <input type="email" class="form-control" id="email" name="email" required>
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
