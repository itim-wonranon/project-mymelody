<?php
require_once 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_report'])) {
    $issue_type = $_POST['issue_type'];
    $description = $_POST['description'];
    
    if (!empty($description) && !empty($issue_type)) {
        $stmt = $conn->prepare("INSERT INTO disputes (user_id, issue_type, description) VALUES (?, ?, ?)");
        if ($stmt->execute([$user_id, $issue_type, $description])) {
            $success = "ส่งเรื่องแจ้งปัญหาเรียบร้อยแล้ว ทีมงานจะรีบตรวจสอบให้เร็วที่สุด";
        } else {
            $error = "เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง";
        }
    } else {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    }
}

// Fetch user's previous reports
$stmt = $conn->prepare("SELECT * FROM disputes WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$reports = $stmt->fetchAll();

?>
<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm mb-4 border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>แจ้งปัญหาการใช้งาน / ร้องเรียน</h5>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>

                    <form method="POST" action="report_issue.php">
                        <input type="hidden" name="submit_report" value="1">
                        <div class="mb-3">
                            <label class="form-label">ประเภทปัญหา</label>
                            <select name="issue_type" class="form-select" required>
                                <option value="">-- เลือกประเภทปัญหา --</option>
                                <option value="นักดนตรีเบี้ยวงาน/ไม่มาตามนัด">นักดนตรีเบี้ยวงาน/ไม่มาตามนัด</option>
                                <option value="ผู้ว่าจ้างยกเลิกงานกะทันหัน">ผู้ว่าจ้างยกเลิกงานกะทันหัน</option>
                                <option value="ปัญหาการชำระเงิน">ปัญหาการชำระเงิน</option>
                                <option value="พฤติกรรมไม่เหมาะสม">พฤติกรรมไม่เหมาะสม</option>
                                <option value="ปัญหาการใช้งานเว็บไซต์ (Bug)">ปัญหาการใช้งานเว็บไซต์ (Bug)</option>
                                <option value="อื่นๆ">อื่นๆ</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">รายละเอียด</label>
                            <textarea name="description" class="form-control" rows="5" required placeholder="กรุณาระบุรายละเอียดให้ชัดเจน เช่น วันที่เกิดเหตุ ชื่อคู่กรณี เป็นต้น"></textarea>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-danger rounded-pill px-4">ส่งเรื่องแจ้งปัญหา</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Previous Reports -->
            <h5 class="fw-bold mb-3 mt-5">ประวัติการแจ้งปัญหาของฉัน</h5>
            <?php if (count($reports) > 0): ?>
                <?php foreach ($reports as $report): ?>
                    <div class="card shadow-sm mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h6 class="fw-bold text-danger"><?php echo htmlspecialchars($report['issue_type']); ?></h6>
                                <?php if ($report['status'] === 'open'): ?>
                                    <span class="badge bg-warning text-dark">กำลังดำเนินการ</span>
                                <?php else: ?>
                                    <span class="badge bg-success">แก้ไขแล้ว</span>
                                <?php endif; ?>
                            </div>
                            <p class="mb-1 mt-2 text-muted"><?php echo nl2br(htmlspecialchars($report['description'])); ?></p>
                            <small class="text-muted">แจ้งเมื่อ: <?php echo date('d/m/Y H:i', strtotime($report['created_at'])); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">ยังไม่มีประวัติการแจ้งปัญหา</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
