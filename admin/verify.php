<?php
include 'header.php';

$success = '';

// Handle Verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_user'])) {
    $musician_id = $_POST['musician_id'];
    $status = $_POST['status']; // 1 for verify, 0 for unverify
    
    $stmt = $conn->prepare("UPDATE musician_profiles SET is_verified = ? WHERE user_id = ?");
    if ($stmt->execute([$status, $musician_id])) {
        $success = "อัปเดตสถานะการยืนยันตัวตนเรียบร้อยแล้ว";
    }
}

// Fetch Musicians
$stmt = $conn->query("
    SELECT u.id, u.username, u.email, m.is_verified, m.created_at
    FROM users u
    JOIN musician_profiles m ON u.id = m.user_id
    WHERE u.role = 'musician'
    ORDER BY m.created_at DESC
");
$musicians = $stmt->fetchAll();
?>

    <h2 class="mb-4 fw-bold">ยืนยันตัวตนนักดนตรี</h2>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($musicians as $m): ?>
                        <tr>
                            <td><?php echo $m['id']; ?></td>
                            <td><?php echo htmlspecialchars($m['username']); ?></td>
                            <td><?php echo htmlspecialchars($m['email']); ?></td>
                            <td>
                                <?php if ($m['is_verified']): ?>
                                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>ยืนยันแล้ว</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>รอตรวจสอบ</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="verify.php" class="d-inline">
                                    <input type="hidden" name="verify_user" value="1">
                                    <input type="hidden" name="musician_id" value="<?php echo $m['id']; ?>">
                                    <?php if ($m['is_verified']): ?>
                                        <input type="hidden" name="status" value="0">
                                        <button type="submit" class="btn btn-sm btn-danger">ยกเลิกการยืนยัน</button>
                                    <?php else: ?>
                                        <input type="hidden" name="status" value="1">
                                        <button type="submit" class="btn btn-sm btn-success">อนุมัติการยืนยัน</button>
                                    <?php endif; ?>
                                </form>
                                <a href="../musician_profile.php?id=<?php echo $m['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> ดูโปรไฟล์</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

        </div> <!-- End Main Content -->
    </div> <!-- End Row -->
</div> <!-- End Container Fluid -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
