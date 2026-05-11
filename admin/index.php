<?php
include 'header.php';

// Fetch Statistics
$stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'musician'");
$total_musicians = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'employer'");
$total_employers = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM bookings");
$total_bookings = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM disputes WHERE status = 'open'");
$open_disputes = $stmt->fetchColumn();

?>
    <h2 class="mb-4 fw-bold">Dashboard</h2>
    
    <div class="row">
        <!-- Stats Cards -->
        <div class="col-md-3 mb-4">
            <div class="card bg-primary text-white h-100 shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">นักดนตรีทั้งหมด</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $total_musicians; ?></h2>
                        </div>
                        <i class="fas fa-guitar fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card bg-success text-white h-100 shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">ผู้ว่าจ้างทั้งหมด</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $total_employers; ?></h2>
                        </div>
                        <i class="fas fa-briefcase fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card bg-info text-white h-100 shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">รายการจองงานทั้งหมด</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $total_bookings; ?></h2>
                        </div>
                        <i class="far fa-calendar-check fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card bg-danger text-white h-100 shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">เรื่องร้องเรียนใหม่</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $open_disputes; ?></h2>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Users -->
    <div class="card shadow mt-4">
        <div class="card-header bg-white"><h5 class="mb-0 fw-bold">ผู้สมัครสมาชิกล่าสุด</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>วันที่สมัคร</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
                        while ($row = $stmt->fetch()):
                        ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td>
                                <?php if($row['role'] == 'admin') echo '<span class="badge bg-dark">Admin</span>'; ?>
                                <?php if($row['role'] == 'musician') echo '<span class="badge bg-primary">นักดนตรี</span>'; ?>
                                <?php if($row['role'] == 'employer') echo '<span class="badge bg-success">ผู้ว่าจ้าง</span>'; ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
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
