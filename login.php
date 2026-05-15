<?php
require_once 'includes/db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            if ($user['role'] === 'admin') {
                header("Location: admin/index.php");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            $error = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง";
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
            <h1 class="login-title">WELCOME BACK<br>TO <span class="text-primary" style="font-style: italic; letter-spacing: 1px;">MY MELODY</span></h1>
            <p class="login-subtitle mt-3">ค้นพบโอกาสงานดนตรีและเชื่อมต่อกับนักดนตรีมากความสามารถ</p>
        </div>
    </div>
    <div class="login-right">
        <div class="login-form-container">
            <div class="text-center mb-5">
                <div class="mb-4 d-inline-block p-3 rounded-circle" style="background: rgba(196, 113, 237, 0.1); border: 1px solid rgba(196, 113, 237, 0.2);">
                    <i class="fas fa-headphones-alt text-primary" style="font-size: 3rem; filter: drop-shadow(0 0 10px rgba(196, 113, 237, 0.5));"></i>
                </div>
                <h2 class="fw-bold fs-1">เข้าสู่ระบบ</h2>
                <p class="text-secondary mt-2">เข้าสู่ระบบเพื่อดำเนินการต่อ</p>
            </div>
            
            <?php if (isset($_SESSION['success_msg'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ!',
                            text: '<?php echo $_SESSION['success_msg']; ?>',
                            background: '#15151c',
                            color: '#ffffff',
                            confirmButtonColor: '#c471ed',
                            confirmButtonText: 'ตกลง',
                            customClass: {
                                popup: 'border border-secondary rounded-4'
                            }
                        });
                    });
                </script>
                <?php unset($_SESSION['success_msg']); ?>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 rounded-4" role="alert" style="background: rgba(220,53,69,0.1); color: #ff6b6b; padding: 1rem 1.25rem;">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="mt-4">
                <div class="mb-4">
                    <label for="username" class="form-label text-secondary fw-semibold">ชื่อผู้ใช้งาน</label>
                    <div class="input-group login-input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="กรอกชื่อผู้ใช้งานของคุณ" required>
                    </div>
                </div>
                
                <div class="mb-5">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label for="password" class="form-label text-secondary fw-semibold mb-0">รหัสผ่าน</label>
                        <a href="#" class="text-primary small text-decoration-none hover-glow">ลืมรหัสผ่าน?</a>
                    </div>
                    <div class="input-group login-input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
                        <button class="btn btn-outline-secondary border-start-0" type="button" id="togglePassword">
                            <i class="fas fa-eye text-muted" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="d-grid mb-5">
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill glow-btn fw-bold py-3">เข้าสู่ระบบ <i class="fas fa-arrow-right ms-2"></i></button>
                </div>
            </form>
            
            <div class="text-center mt-auto">
                <p class="mb-0 text-secondary">ยังไม่มีบัญชี? <a href="register.php" class="text-primary fw-bold text-decoration-none hover-glow ms-1">สมัครสมาชิกที่นี่</a></p>
            </div>
        </div>
    </div>
</div>

<?php 
$hide_footer = true;
include 'includes/footer.php'; 
?>
