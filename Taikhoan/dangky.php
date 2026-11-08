<?php
session_start();

$captcha_a = random_int(1, 9);
$captcha_b = random_int(1, 9);
$_SESSION['captcha_answer'] = $captcha_a + $captcha_b;

$captcha_error = isset($_GET['error']) && $_GET['error'] === 'captcha';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký tài khoản - Kaido</title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="../css/dndk.css">
</head>
<body>

    <!-- Nút chuyển đổi Dark / Light mode -->
    <button id="themeToggle" class="auth-theme-btn" onclick="toggleTheme()">
        🌙 Dark
    </button>

<div class="auth-box">

    <h2>Đăng ký tài khoản</h2>

    <?php if ($captcha_error): ?>
        <p role="alert" style="color: #ff416c; text-align: center; margin-bottom: 15px; font-size: 14px;">CAPTCHA không đúng. Vui lòng thử lại.</p>
    <?php endif; ?>

    <form action="xulydangky.php" method="POST">

        <div class="input-group">
            <label>Tên đăng nhập</label>
            <input type="text" name="username" placeholder="Nhập tên đăng nhập" required>
        </div>

         <div class="input-group">
            <label>Họ và tên</label>
            <input type="text" name="full_name" placeholder="Nhập họ và tên" required>
        </div>

        <div class="input-group">
            <label>Email</label>
            <input type="email" name="email" placeholder="Nhập email" required>
        </div>

        <div class="input-group">
            <label>Mật khẩu</label>
            <input type="password" name="password" placeholder="Nhập mật khẩu" required>
        </div>

        <div class="input-group">
            <label>Nhập lại mật khẩu</label>
            <input type="password" name="confirm_password" placeholder="Nhập lại mật khẩu" required>
        </div>

        <div class="input-group">
            <label>Xác thực CAPTCHA: <?= $captcha_a ?> + <?= $captcha_b ?> = ?</label>
            <input type="number" name="captcha_answer" placeholder="Nhập kết quả" required>
        </div>

        <button type="submit">Đăng ký</button>

    </form>

    <p>
        Đã có tài khoản?
        <a href="dangnhap.php">Đăng nhập</a>
    </p>

    <a href="../index.php" class="back-home">
        ← Trở về trang chủ
    </a>

</div>
</body>
<script src="../js/slide.js"></script>
</html>