<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/otp_helper.php';

$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    if (!pr_csrf_ok()) {
        $err = 'Phiên làm việc hết hạn, vui lòng thử lại.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $err = 'Email không hợp lệ.';
    } else {
        $r = pr_request_otp($conn, $email);
        if ($r['status'] === 'mail_error' && !empty(pr_config()['hien_loi_gui_mail'])) {
            $err = 'Không gửi được email: ' . $r['detail'];
        } elseif ($r['status'] === 'cooldown') {
            $err = 'Vui lòng đợi ' . (int)$r['detail'] . ' giây rồi thử lại.';
        } elseif ($r['status'] === 'limit') {
            $err = 'Bạn đã yêu cầu quá nhiều lần. Vui lòng thử lại sau 1 giờ.';
        } else {
            // Luôn thông báo chung, kể cả khi email không tồn tại
            session_regenerate_id(true);
            $_SESSION['pr_email'] = $email;
            unset($_SESSION['pr_reset_id']);
            header('Location: xacnhanotp.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu - Kaido</title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="../css/dndk.css">
    <style>
        .msg{padding:10px 14px;border-radius:8px;margin:0 0 14px;font-size:14px;line-height:1.45;text-align:left}
        .msg.err{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.5);color:#ef4444}
        .msg.ok{background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.5);color:#16a34a}
        .otp-input{text-align:center;font-size:26px;letter-spacing:10px;font-weight:700}
        .sub-note{font-size:14px;opacity:.8;margin:0 0 14px;line-height:1.5}
        .linkbtn{background:none;border:0;padding:0;color:#ff416c;cursor:pointer;font:inherit;text-decoration:underline;width:auto;display:inline}
    </style>
</head>
<body>
    <button id="themeToggle" class="auth-theme-btn" onclick="toggleTheme()">🌙 Dark</button>
<div class="auth-box">
    <h2>Quên mật khẩu</h2>
    <p class="sub-note">Nhập email đã đăng ký. Chúng tôi sẽ gửi mã OTP gồm 6 chữ số đến email đó.</p>
    <?php if ($err !== ''): ?><div class="msg err"><?= pr_h($err) ?></div><?php endif; ?>
    <form method="POST" autocomplete="off">
        <?= pr_csrf_field() ?>
        <div class="input-group">
            <label>Email</label>
            <input type="email" name="email" placeholder="Nhập email của bạn" maxlength="150" value="<?= pr_h($_POST['email'] ?? '') ?>" required autofocus>
        </div>
        <button type="submit">Gửi mã OTP</button>
    </form>
    <p><a href="dangnhap.php">← Quay lại đăng nhập</a></p>
</div>
</body>
<script src="../js/slide.js"></script>
</html>
