<nav class="navbar navbar-expand-lg fixed-top" id="mainNav">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            MY MELODY
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fas fa-bars text-white"></i>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">หน้าแรก</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="search.php">ค้นหานักดนตรี</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="community.php">Community</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php
                        $booking_notif_count = 0;
                        if ($_SESSION['role'] === 'musician') {
                            $stmt_bn = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE musician_id = ? AND status = 'pending' AND is_read_musician = 0");
                            $stmt_bn->execute([$_SESSION['user_id']]);
                            $booking_notif_count = $stmt_bn->fetchColumn();
                        } else if ($_SESSION['role'] === 'employer') {
                            $stmt_bn = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE employer_id = ? AND status IN ('confirmed', 'rejected') AND is_read_employer = 0");
                            $stmt_bn->execute([$_SESSION['user_id']]);
                            $booking_notif_count = $stmt_bn->fetchColumn();
                        }
                    ?>
                    <!-- Booking Notification Icon -->
                    <?php if ($_SESSION['role'] !== 'admin'): ?>
                    <li class="nav-item me-2 me-lg-3">
                        <a class="nav-link position-relative d-flex align-items-center justify-content-center border border-primary border-opacity-25 shadow-sm" href="#" data-bs-toggle="modal" data-bs-target="#bookingNotifModal" title="การนัดหมาย/คิวงาน" style="width: 42px; height: 42px; background: rgba(196, 113, 237, 0.15); border-radius: 50%; transition: all 0.3s ease;">
                            <i class="fas fa-calendar-alt fs-5 text-primary" style="filter: drop-shadow(0 0 5px rgba(196, 113, 237, 0.5));"></i>
                            <?php if ($booking_notif_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-dark" style="font-size: 0.7rem;">
                                    <?php echo $booking_notif_count > 99 ? '99+' : $booking_notif_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?php echo isset($_SESSION['profile_image']) && $_SESSION['profile_image'] !== 'default_avatar.png' ? 'uploads/avatars/' . $_SESSION['profile_image'] : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['username']) . '&background=c471ed&color=fff'; ?>"
                                alt="User" width="30" height="30" class="rounded-circle me-2" style="object-fit: cover;">
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="navbarDropdown">
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <li><a class="dropdown-item" href="admin/index.php"><i
                                            class="fas fa-tachometer-alt me-2 text-primary"></i>Admin Dashboard</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="profile.php"><i
                                            class="fas fa-user me-2 text-primary"></i>โปรไฟล์ของฉัน</a></li>
                                <li><a class="dropdown-item" href="report_issue.php"><i
                                            class="fas fa-exclamation-triangle me-2 text-warning"></i>แจ้งปัญหา</a></li>
                            <?php endif; ?>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i
                                        class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">เข้าสู่ระบบ</a>
                    </li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a class="btn btn-primary rounded-pill px-4" href="register.php">สร้างบัญชี</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Booking Notifications Modal -->
<div class="modal fade" id="bookingNotifModal" tabindex="-1" aria-labelledby="bookingNotifModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 650px;">
        <div class="modal-content glass-card-premium border-primary shadow-lg" style="background: rgba(20, 20, 25, 0.95); backdrop-filter: blur(16px);">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-white" id="bookingNotifModalLabel"><i class="fas fa-calendar-alt text-primary me-2"></i>การแจ้งเตือนคิวงาน</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="bookingNotifContainer">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-secondary mt-3 mb-0">กำลังโหลดข้อมูล...</p>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary border-opacity-25 justify-content-center">
                <a href="booking.php" class="btn btn-outline-primary rounded-pill w-100 glow-btn">ดูการนัดหมายทั้งหมด <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bookingModal = document.getElementById('bookingNotifModal');
    if (bookingModal) {
        bookingModal.addEventListener('show.bs.modal', function () {
            const container = document.getElementById('bookingNotifContainer');
            // Fetch via AJAX
            fetch('ajax_booking_notifications.php')
                .then(response => response.text())
                .then(html => {
                    container.innerHTML = html;
                })
                .catch(err => {
                    container.innerHTML = '<div class="text-center py-4 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
                });
        });
    }
});
</script>