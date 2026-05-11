<?php
include 'header.php';

$success = '';

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $dispute_id = $_POST['dispute_id'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE disputes SET status = ? WHERE id = ?");
    if ($stmt->execute([$status, $dispute_id])) {
        $success = "อัปเดตสถานะปัญหาเรียบร้อยแล้ว";
    }
}

// Fetch Disputes
$stmt = $conn->query("
    SELECT d.*, u.username, u.role, u.email 
    FROM disputes d 
    JOIN users u ON d.user_id = u.id 
    ORDER BY d.created_at DESC
");
$disputes = $stmt->fetchAll();
?>

    <h2 class="mb-4 fw-bold">ระบบจัดการการแจ้งปัญหา (Disputes)</h2>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card shadow border-danger">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>ผู้แจ้ง</th>
                            <th>ประเภทปัญหา</th>
                            <th>รายละเอียด</th>
                            <th>วันที่แจ้ง</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($disputes) > 0): ?>
                            <?php foreach ($disputes as $d): ?>
                            <tr>
                                <td><?php echo $d['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($d['username']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($d['email']); ?></small>
                                </td>
                                <td><span class="text-danger fw-bold"><?php echo htmlspecialchars($d['issue_type']); ?></span></td>
                                <td style="max-width: 300px;">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="popover" title="รายละเอียด" data-bs-content="<?php echo htmlspecialchars($d['description']); ?>">
                                        ดูรายละเอียด
                                    </button>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($d['created_at'])); ?></td>
                                <td>
                                    <?php if ($d['status'] == 'open'): ?>
                                        <span class="badge bg-warning text-dark">เปิดอยู่ (รอดำเนินการ)</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">แก้ไขแล้ว</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" action="disputes.php">
                                        <input type="hidden" name="update_status" value="1">
                                        <input type="hidden" name="dispute_id" value="<?php echo $d['id']; ?>">
                                        <?php if ($d['status'] == 'open'): ?>
                                            <input type="hidden" name="status" value="resolved">
                                            <button type="submit" class="btn btn-sm btn-success">ทำเครื่องหมายว่าแก้ไขแล้ว</button>
                                        <?php else: ?>
                                            <input type="hidden" name="status" value="open">
                                            <button type="submit" class="btn btn-sm btn-warning">เปิดเรื่องใหม่</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">ไม่มีประวัติการแจ้งปัญหา</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

        </div> <!-- End Main Content -->
    </div> <!-- End Row -->
</div> <!-- End Container Fluid -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl)
    })
});
</script>
</body>
</html>
