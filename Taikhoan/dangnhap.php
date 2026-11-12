<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập vào Kaido</title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="../css/dndk.css">
</head>
<body>
    <!-- Nút chuyển đổi Dark / Light mode -->
    <button id="themeToggle" class="auth-theme-btn" onclick="toggleTheme()">
        🌙 Dark
    </button>
<div class="auth-box">
    <h2>Đăng nhập vào Kaido</h2>
    <?php if (($_GET['reset'] ?? '') === 'ok'): ?>
        <div style="padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:14px;background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.5);color:#16a34a">Đổi mật khẩu thành công! Hãy đăng nhập bằng mật khẩu mới.</div>
    <?php endif; ?>
    <form action="xulydangnhap.php" method="POST">
        <div class="input-group">
            <label>Tên đăng nhập</label>
            <input type="text" name="username" placeholder="Nhập tên đăng nhập" required>
        </div>
        <div class="input-group">
            <label>Mật khẩu</label>
            <input type="password" name="password" placeholder="Nhập mật khẩu" required>
        </div>
        <button type="submit">Đăng nhập</button>
    </form>
    <p style="margin-top:12px"><a href="quenmatkhau.php">Quên mật khẩu?</a></p>
    <p>
        Chưa có tài khoản?
        <a href="dangky.php">Đăng ký ngay</a>
    </p>
    <a href="../index.php" class="back-home">
        ← Về trang chủ
    </a>
</div>
</body>
<script src="../js/slide.js"></script>
</html>