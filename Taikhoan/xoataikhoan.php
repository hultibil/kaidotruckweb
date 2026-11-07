<?php
session_start();

// Bắt buộc phải đăng nhập mới được vào trang này
if (!isset($_SESSION['user_id'])) {
    header("Location: dangnhap.php");
    exit;
}

// Tạo CSRF token nếu chưa có để chống tấn công giả mạo request
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xác nhận xóa tài khoản - KAIDO</title>
    <link rel="stylesheet" href="../css/xoataikhoan.css">
</head>
<body>

    <h2>XÓA TÀI KHOẢN</h2>

    <p style="color: red;">
        Cảnh báo: Bạn đang chuẩn bị xóa tài khoản KAIDO. 
        Hành động này sẽ xóa vĩnh viễn dữ liệu và không thể hoàn tác!
    </p>

    <!-- Hiển thị thông báo lỗi nếu có từ file xử lý gửi sang -->
    <?php if (isset($_SESSION['error_message'])): ?>
        <p style="color: red; font-weight: bold;"><?= htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php unset($_SESSION['error_message']); // Xóa sau khi hiển thị ?>
    <?php endif; ?>

    <form
        action="xulyxoataikhoan.php"
        method="POST"
        onsubmit="return confirm('Bạn chắc chắn muốn xóa vĩnh viễn tài khoản này chứ?');"
    >
        <!-- Token chống CSRF -->
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
        >

        <label>Nhập mật khẩu hiện tại để xác nhận:</label>
        <br>
        <input
            type="password"
            name="password"
            required
            autocomplete="current-password"
        >
        <br><br>

        <button type="submit" style="background-color: red; color: white; padding: 10px;">Xác nhận xóa tài khoản</button>
    </form>

    <br>
    <a href="taikhoan.php">Quay lại trang tài khoản</a>

</body>
</html>