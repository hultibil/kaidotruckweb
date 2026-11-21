<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Lấy thông báo lỗi và username cũ (nếu vừa đăng nhập sai)
$loginError = $_SESSION['login_error'] ?? '';
$oldUsername = $_SESSION['login_old_user'] ?? '';
unset($_SESSION['login_error'], $_SESSION['login_old_user']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập vào Kaido</title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="../css/dndk.css">
    <link rel="stylesheet" href="../css/glassy.css">
    <script src="../js/glassy-theme.js"></script>
    <style>
        .auth-alert {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 13.5px;
            font-weight: 600;
            line-height: 1.45;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .auth-alert.error {
            background: rgba(239, 68, 68, 0.16);
            border: 1px solid rgba(239, 68, 68, 0.45);
            color: #dc2626;
        }
        .auth-alert.success {
            background: rgba(34, 197, 94, 0.16);
            border: 1px solid rgba(34, 197, 94, 0.45);
            color: #16a34a;
        }
        html[data-glassy-theme="dark"] .auth-alert.error { color: #fca5a5; }
        html[data-glassy-theme="dark"] .auth-alert.success { color: #86efac; }
    </style>
</head>
<body class="glassy-page">
<?php $glassyBase = '../'; require __DIR__ . '/../layout/glassy-sidebar.php'; ?>
<div id="glassy-content" class="glassy-content" tabindex="-1">

    <div class="auth-box">
        <h2>Đăng nhập vào Kaido</h2>

        <?php if (($_GET['reset'] ?? '') === 'ok'): ?>
            <div class="auth-alert success" role="status">
                <span>✅</span>
                <span>Đổi mật khẩu thành công! Hãy đăng nhập bằng mật khẩu mới.</span>
            </div>
        <?php endif; ?>

        <?php if ($loginError !== ''): ?>
            <div class="auth-alert error" role="alert">
                <span>⚠️</span>
                <span><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <form action="xulydangnhap.php" method="POST">
            <div class="input-group">
                <label>Tên đăng nhập</label>
                <input type="text" name="username" placeholder="Nhập tên đăng nhập" value="<?= htmlspecialchars($oldUsername, ENT_QUOTES, 'UTF-8') ?>" required autofocus>
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

</div><!-- glassy-content -->
</body>
<script src="../js/slide.js"></script>
</html>