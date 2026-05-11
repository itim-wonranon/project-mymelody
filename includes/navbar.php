<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="fas fa-music me-2"></i>MuseConnect
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-toggle="target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
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
                    <a class="nav-link" href="feed.php">กระดานข่าวสาร</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <li><a class="dropdown-item" href="admin/index.php">Admin Dashboard</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="profile.php">โปรไฟล์ของฉัน</a></li>
                                <li><a class="dropdown-item" href="booking.php">การนัดหมาย/คิวงาน</a></li>
                                <li><a class="dropdown-item" href="report_issue.php">แจ้งปัญหา</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php">ออกจากระบบ</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">เข้าสู่ระบบ</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-outline-light ms-2 rounded-pill" href="register.php">สมัครสมาชิก</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
