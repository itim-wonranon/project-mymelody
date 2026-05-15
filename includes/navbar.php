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
                                <li><a class="dropdown-item" href="booking.php"><i
                                            class="fas fa-calendar-alt me-2 text-primary"></i>การนัดหมาย/คิวงาน</a></li>
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