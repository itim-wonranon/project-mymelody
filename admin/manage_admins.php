<?php
include 'header.php';

// Only Super Admins can access this page
if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    echo "<div class='alert alert-danger m-4'>Access Denied. You do not have permission to view this page.</div>";
    exit();
}

$success = '';
$error = '';

// Handle Create Sub Admin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_admin'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($username) || empty($email) || empty($password)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } else {
        // Handle permissions
        $permissions = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];
        $permissions_json = json_encode($permissions);

        // Check if username or email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = "ชื่อผู้ใช้หรืออีเมลนี้มีในระบบแล้ว";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, is_super_admin, is_approved, admin_permissions) VALUES (?, ?, ?, 'admin', 0, 1, ?)");
            if ($stmt->execute([$username, $email, $hashed_password, $permissions_json])) {
                $success = "สร้างบัญชีผู้ช่วยแอดมินเรียบร้อยแล้ว";
            } else {
                $error = "เกิดข้อผิดพลาดในการสร้างบัญชี";
            }
        }
    }
}

// Handle Update Permissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_permissions'])) {
    $admin_id = $_POST['admin_id'];
    $permissions = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];
    $permissions_json = json_encode($permissions);
    
    if ($admin_id == $_SESSION['user_id']) {
        $error = "ไม่สามารถแก้ไขสิทธิ์ของตัวเองได้";
    } else {
        $stmt = $conn->prepare("UPDATE users SET admin_permissions = ? WHERE id = ? AND role = 'admin' AND is_super_admin = 0");
        if ($stmt->execute([$permissions_json, $admin_id])) {
            $success = "อัปเดตสิทธิ์การใช้งานเรียบร้อยแล้ว";
        } else {
            $error = "เกิดข้อผิดพลาดในการอัปเดตสิทธิ์";
        }
    }
}

// Handle Approve / Revoke Admin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_approval'])) {
    $admin_id = $_POST['admin_id'];
    $status = $_POST['status']; // 1 or 0
    
    // Prevent modifying oneself
    if ($admin_id == $_SESSION['user_id']) {
        $error = "ไม่สามารถแก้ไขสิทธิ์ของตัวเองได้";
    } else {
        $stmt = $conn->prepare("UPDATE users SET is_approved = ? WHERE id = ? AND role = 'admin'");
        if ($stmt->execute([$status, $admin_id])) {
            $success = "อัปเดตสถานะบัญชีเรียบร้อยแล้ว";
        }
    }
}

// Fetch all admins
$stmt = $conn->query("SELECT id, username, email, is_super_admin, is_approved, admin_permissions, created_at FROM users WHERE role = 'admin' ORDER BY created_at DESC");
$admins = $stmt->fetchAll();
?>

    <h2 class="mb-4 fw-bold">จัดการผู้ดูแลระบบ (Manage Admins)</h2>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row">
        <!-- Create Admin Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow border-0 rounded-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h5 class="fw-bold mb-0"><i class="fas fa-user-plus text-primary me-2"></i>สร้างผู้ช่วยแอดมินใหม่</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <input type="hidden" name="create_admin" value="1">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">ชื่อผู้ใช้งาน (Username)</label>
                            <input type="text" name="username" class="form-control bg-light border-0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">อีเมล (Email)</label>
                            <input type="email" name="email" class="form-control bg-light border-0" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold">รหัสผ่าน (Password)</label>
                            <input type="password" name="password" class="form-control bg-light border-0" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold">สิทธิ์การใช้งาน (Permissions)</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="view_dashboard" id="perm_dash" checked>
                                <label class="form-check-label" for="perm_dash">📊 ดูภาพรวมระบบ (Dashboard)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="manage_verify" id="perm_verify">
                                <label class="form-check-label" for="perm_verify">👤 ยืนยันตัวตนศิลปิน (Verification)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="manage_disputes" id="perm_disputes">
                                <label class="form-check-label" for="perm_disputes">⚖️ จัดการข้อพิพาท (Disputes)</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm">สร้างบัญชี</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Admins List -->
        <div class="col-md-8 mb-4">
            <div class="card shadow border-0 rounded-4 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h5 class="fw-bold mb-0"><i class="fas fa-users text-primary me-2"></i>รายชื่อผู้ดูแลระบบทั้งหมด</h5>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>ประเภท</th>
                                    <th>สถานะ</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $a): ?>
                                <tr>
                                    <td><?php echo $a['id']; ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($a['username']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($a['email']); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($a['is_super_admin']): ?>
                                            <span class="badge bg-danger">Super Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Sub Admin</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($a['is_approved']): ?>
                                            <span class="badge bg-success">อนุมัติแล้ว (Active)</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">ถูกระงับ (Suspended/Pending)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$a['is_super_admin']): ?>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="update_approval" value="1">
                                                <input type="hidden" name="admin_id" value="<?php echo $a['id']; ?>">
                                                <?php if ($a['is_approved']): ?>
                                                    <input type="hidden" name="status" value="0">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('คุณต้องการระงับบัญชีนี้ใช่หรือไม่?');">ระงับการใช้งาน</button>
                                                <?php else: ?>
                                                    <input type="hidden" name="status" value="1">
                                                    <button type="submit" class="btn btn-sm btn-success">อนุมัติบัญชี</button>
                                                <?php endif; ?>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPermModal<?php echo $a['id']; ?>">แก้ไขสิทธิ์</button>
                                            
                                            <!-- Edit Permissions Modal -->
                                            <div class="modal fade" id="editPermModal<?php echo $a['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">แก้ไขสิทธิ์ของ <?php echo htmlspecialchars($a['username']); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST" action="">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="update_permissions" value="1">
                                                                <input type="hidden" name="admin_id" value="<?php echo $a['id']; ?>">
                                                                <?php 
                                                                    $perms = json_decode($a['admin_permissions'], true) ?? []; 
                                                                ?>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="view_dashboard" id="perm_dash_<?php echo $a['id']; ?>" <?php echo in_array('view_dashboard', $perms) ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="perm_dash_<?php echo $a['id']; ?>">📊 ดูภาพรวมระบบ (Dashboard)</label>
                                                                </div>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="manage_verify" id="perm_verify_<?php echo $a['id']; ?>" <?php echo in_array('manage_verify', $perms) ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="perm_verify_<?php echo $a['id']; ?>">👤 ยืนยันตัวตนศิลปิน (Verification)</label>
                                                                </div>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="manage_disputes" id="perm_disputes_<?php echo $a['id']; ?>" <?php echo in_array('manage_disputes', $perms) ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="perm_disputes_<?php echo $a['id']; ?>">⚖️ จัดการข้อพิพาท (Disputes)</label>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                                                <button type="submit" class="btn btn-primary">บันทึกสิทธิ์</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">แก้ไขไม่ได้</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

        </div> <!-- End Main Content -->
    </div> <!-- End Row -->
</div> <!-- End Container Fluid -->

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
