<?php
include 'header.php';

// Check permissions
$is_super = isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'];
$perms = $_SESSION['admin_permissions'] ?? [];
if (!$is_super && !in_array('view_dashboard', $perms)) {
    echo "<div class='alert alert-danger m-4'>Access Denied. You do not have permission to view this page.</div></div></div></div></body></html>";
    exit();
}

// --- Fetch Statistics ---

// 1. Total Users Breakdown and Lists
$stmt = $conn->query("SELECT role, COUNT(*) as count FROM users WHERE role IN ('musician', 'employer') GROUP BY role");
$user_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$total_musicians = $user_counts['musician'] ?? 0;
$total_employers = $user_counts['employer'] ?? 0;
$total_users = $total_musicians + $total_employers;

// Fetch lists of users for the chart click modal
$stmt = $conn->query("SELECT id, username, email FROM users WHERE role = 'musician' ORDER BY created_at DESC");
$list_musicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->query("SELECT id, username, email FROM users WHERE role = 'employer' ORDER BY created_at DESC");
$list_employers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Booking Status Breakdown
$stmt = $conn->query("SELECT status, COUNT(*) as count FROM bookings GROUP BY status");
$booking_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$active_bookings = ($booking_counts['pending'] ?? 0) + ($booking_counts['confirmed'] ?? 0);
$rejected_bookings = $booking_counts['rejected'] ?? 0;
$completed_bookings = $booking_counts['completed'] ?? 0;

// 3. Action Required
$stmt = $conn->query("SELECT COUNT(*) FROM disputes WHERE status = 'open'");
$open_disputes = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM musician_profiles WHERE is_verified = 0");
$unverified_musicians = $stmt->fetchColumn();
$total_action_needed = $open_disputes + $unverified_musicians;

?>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">Dashboard Overview</h2>
        <span class="text-muted"><i class="fas fa-calendar-alt me-2"></i><?php echo date('d M Y'); ?></span>
    </div>
    
    <!-- Top Metrics Cards -->
    <div class="row g-4 mb-4">
        <!-- Total Users -->
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded-4 h-100" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(13, 110, 253, 0.1);">
                            <i class="fas fa-users text-primary fs-4"></i>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2">ทั้งหมด <?php echo $total_users; ?> คน</span>
                    </div>
                    <h6 class="text-muted fw-bold mb-1">ผู้ใช้งานระบบ</h6>
                    <div class="d-flex justify-content-between align-items-end">
                        <h2 class="fw-bold mb-0"><?php echo $total_users; ?></h2>
                        <div class="text-end small">
                            <div class="mb-1"><span class="text-primary fw-bold"><i class="fas fa-guitar me-1"></i>นักดนตรี: <?php echo $total_musicians; ?></span></div>
                            <div><span class="text-success fw-bold"><i class="fas fa-briefcase me-1"></i>ผู้ว่าจ้าง: <?php echo $total_employers; ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Bookings -->
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded-4 h-100" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(255, 193, 7, 0.1);">
                            <i class="fas fa-clock text-warning fs-4"></i>
                        </div>
                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-2">รอดำเนินการ / ยืนยันแล้ว</span>
                    </div>
                    <h6 class="text-muted fw-bold mb-1">คิวงานที่กำลังดำเนินการ</h6>
                    <h2 class="fw-bold mb-0"><?php echo $active_bookings; ?></h2>
                </div>
            </div>
        </div>

        <!-- Rejected / Completed Bookings -->
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded-4 h-100" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(25, 135, 84, 0.1);">
                            <i class="fas fa-check-circle text-success fs-4"></i>
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2">สิ้นสุดแล้ว</span>
                    </div>
                    <h6 class="text-muted fw-bold mb-1">คิวงานสำเร็จ / ปฏิเสธ</h6>
                    <div class="d-flex justify-content-between align-items-end">
                        <h2 class="fw-bold mb-0 text-success"><?php echo $completed_bookings; ?></h2>
                        <div class="text-end small">
                            <span class="text-danger fw-bold"><i class="fas fa-times-circle me-1"></i>ถูกปฏิเสธ <?php echo $rejected_bookings; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Needed -->
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded-4 h-100 <?php echo $total_action_needed > 0 ? 'border-danger border-2' : ''; ?>" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(220, 53, 69, 0.1);">
                            <i class="fas fa-exclamation-triangle text-danger fs-4"></i>
                        </div>
                        <?php if ($total_action_needed > 0): ?>
                            <span class="badge bg-danger rounded-pill px-3 py-2 animate__animated animate__pulse animate__infinite">ต้องจัดการเร่งด่วน</span>
                        <?php else: ?>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-2">ปกติ</span>
                        <?php endif; ?>
                    </div>
                    <h6 class="text-muted fw-bold mb-1">รายการรอตรวจสอบ</h6>
                    <div class="d-flex justify-content-between align-items-end">
                        <h2 class="fw-bold mb-0 text-danger"><?php echo $total_action_needed; ?></h2>
                        <div class="text-end small">
                            <span class="text-muted">รอยืนยันตัวตน <b class="text-dark"><?php echo $unverified_musicians; ?></b></span><br>
                            <span class="text-muted">ข้อพิพาท <b class="text-dark"><?php echo $open_disputes; ?></b></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-8">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h5 class="fw-bold"><i class="fas fa-chart-bar text-primary me-2"></i>ภาพรวมสถานะการจองงาน</h5>
                </div>
                <div class="card-body">
                    <canvas id="bookingsChart" height="100"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h5 class="fw-bold"><i class="fas fa-chart-pie text-primary me-2"></i>สัดส่วนผู้ใช้งาน</h5>
                </div>
                <div class="card-body d-flex justify-content-center align-items-center">
                    <div style="width: 80%;">
                        <canvas id="usersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="row g-4">
        <!-- Recent Bookings -->
        <div class="col-md-7">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="fas fa-list-alt text-primary me-2"></i>ความเคลื่อนไหวการจองงานล่าสุด</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">วันที่แสดง</th>
                                    <th>ศิลปิน</th>
                                    <th>ผู้ว่าจ้าง</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->query("
                                    SELECT b.id, b.status, b.booking_date, u1.username as employer_name, u2.username as musician_name 
                                    FROM bookings b 
                                    JOIN users u1 ON b.employer_id = u1.id 
                                    JOIN users u2 ON b.musician_id = u2.id 
                                    ORDER BY b.created_at DESC LIMIT 5
                                ");
                                while ($row = $stmt->fetch()):
                                ?>
                                <tr>
                                    <td class="ps-4"><i class="far fa-calendar text-muted me-2"></i><?php echo date('d/m/Y', strtotime($row['booking_date'])); ?></td>
                                    <td class="fw-bold text-primary"><?php echo htmlspecialchars($row['musician_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['employer_name']); ?></td>
                                    <td>
                                        <?php 
                                        if ($row['status'] == 'pending') echo '<span class="badge bg-warning text-dark px-3 rounded-pill">รอดำเนินการ</span>';
                                        elseif ($row['status'] == 'confirmed') echo '<span class="badge bg-info px-3 rounded-pill">ยืนยันแล้ว</span>';
                                        elseif ($row['status'] == 'rejected') echo '<span class="badge bg-danger px-3 rounded-pill">ปฏิเสธ</span>';
                                        elseif ($row['status'] == 'completed') echo '<span class="badge bg-success px-3 rounded-pill">สำเร็จ</span>';
                                        ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="col-md-5">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-user-clock text-primary me-2"></i>สมาชิกล่าสุด</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Username</th>
                                    <th>ประเภท</th>
                                    <th>วันที่สมัคร</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->query("SELECT username, role, created_at FROM users WHERE role != 'admin' ORDER BY created_at DESC LIMIT 5");
                                while ($row = $stmt->fetch()):
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold"><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td>
                                        <?php 
                                        if ($row['role'] == 'musician') echo '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3">นักดนตรี</span>';
                                        elseif ($row['role'] == 'employer') echo '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3">ผู้ว่าจ้าง</span>';
                                        ?>
                                    </td>
                                    <td class="text-muted small"><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- User List Modal -->
    <div class="modal fade" id="userListModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold" id="userListModalTitle">รายชื่อผู้ใช้งาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody id="userListModalBody">
                                <!-- Data injected via JS -->
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

<!-- Chart.js Init -->
<script>
const musiciansData = <?php echo json_encode($list_musicians); ?>;
const employersData = <?php echo json_encode($list_employers); ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Booking Trends Chart
    const ctxBookings = document.getElementById('bookingsChart').getContext('2d');
    new Chart(ctxBookings, {
        type: 'bar',
        data: {
            labels: ['รอดำเนินการ (Pending)', 'ยืนยันแล้ว (Confirmed)', 'สำเร็จ (Completed)', 'ปฏิเสธ (Rejected)'],
            datasets: [{
                label: 'จำนวนคิวงาน',
                data: [
                    <?php echo $booking_counts['pending'] ?? 0; ?>,
                    <?php echo $booking_counts['confirmed'] ?? 0; ?>,
                    <?php echo $booking_counts['completed'] ?? 0; ?>,
                    <?php echo $booking_counts['rejected'] ?? 0; ?>
                ],
                backgroundColor: [
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(13, 202, 240, 0.8)',
                    'rgba(25, 135, 84, 0.8)',
                    'rgba(220, 53, 69, 0.8)'
                ],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });

    // Users Demographics Chart
    const ctxUsers = document.getElementById('usersChart').getContext('2d');
    new Chart(ctxUsers, {
        type: 'doughnut',
        data: {
            labels: ['นักดนตรี', 'ผู้ว่าจ้าง'],
            datasets: [{
                data: [<?php echo $total_musicians; ?>, <?php echo $total_employers; ?>],
                backgroundColor: [
                    'rgba(13, 110, 253, 0.8)',
                    'rgba(25, 135, 84, 0.8)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom' }
            },
            onClick: function(event, elements) {
                if (elements.length > 0) {
                    const index = elements[0].index;
                    let modalTitle = '';
                    let dataList = [];
                    
                    if (index === 0) {
                        modalTitle = 'รายชื่อนักดนตรีทั้งหมด';
                        dataList = musiciansData;
                    } else if (index === 1) {
                        modalTitle = 'รายชื่อผู้ว่าจ้างทั้งหมด';
                        dataList = employersData;
                    }

                    document.getElementById('userListModalTitle').innerText = modalTitle;
                    
                    let tbodyHtml = '';
                    if (dataList.length > 0) {
                        dataList.forEach(user => {
                            tbodyHtml += `
                                <tr>
                                    <td>${user.id}</td>
                                    <td class="fw-bold">${user.username}</td>
                                    <td class="text-muted small">${user.email}</td>
                                </tr>
                            `;
                        });
                    } else {
                        tbodyHtml = '<tr><td colspan="3" class="text-center text-muted py-4">ไม่มีข้อมูล</td></tr>';
                    }
                    
                    document.getElementById('userListModalBody').innerHTML = tbodyHtml;
                    
                    const modal = new bootstrap.Modal(document.getElementById('userListModal'));
                    modal.show();
                }
            }
        }
    });
});
</script>
</body>
</html>
