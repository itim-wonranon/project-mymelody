<?php
require_once 'includes/db.php';

$username = 'admin';
$new_password = 'admin123';
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->execute([$hashed_password, $username]);
    
    if ($stmt->rowCount() > 0) {
        echo "<h2>สำเร็จ!</h2>";
        echo "<p>รหัสผ่านของ <strong>admin</strong> ถูกรีเซ็ตเป็น <strong>admin123</strong> เรียบร้อยแล้ว</p>";
        echo "<a href='login.php'>ไปที่หน้าเข้าสู่ระบบ</a>";
    } else {
        // Maybe the password was already that? Or user doesn't exist?
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            echo "<h2>ข้อมูลถูกต้องอยู่แล้ว</h2>";
            echo "<p>บัญชี admin มีอยู่ในระบบแล้ว และรหัสผ่านอาจจะตรงกันอยู่แล้ว</p>";
        } else {
            // Create it if not exists
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, 'admin@example.com', ?, 'admin')");
            $stmt->execute([$username, $hashed_password]);
            echo "<h2>สร้างบัญชีใหม่สำเร็จ!</h2>";
            echo "<p>สร้างบัญชี <strong>admin</strong> พร้อมรหัสผ่าน <strong>admin123</strong> เรียบร้อยแล้ว</p>";
        }
        echo "<a href='login.php'>ไปที่หน้าเข้าสู่ระบบ</a>";
    }
} catch (PDOException $e) {
    echo "เกิดข้อผิดพลาด: " . $e->getMessage();
}
?>
