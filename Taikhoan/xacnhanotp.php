<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/otp_helper.php';

if (empty($_SESSION['pr_email'])) {
    header('Location: quenmatkhau.php');
    exit;
}
$email = $_SESSION['pr_email'];
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!pr_csrf_ok()) {
        $err = 'Phiên làm việc hết hạn, vui lòng thử lại.';
    } elseif (($_POST['action'] ?? '') === 'resend') {
        $r = pr_request_otp($conn, $email);
        if ($r['status'] === 'mail_error' && !empty(pr_config()['hien_loi_gui_mail'])) {
            $err = 'Không gửi được email: ' . $r['detail'];
        } elseif ($r['status'] === 'cooldown') {
            $err = 'Vui lòng đợi ' . (int)$r['detail'] . ' giây rồi gửi lại.';
        } elseif ($r['status'] === 'limit') {
            $err = 'Bạn đã yêu cầu quá nhiều lần. Vui lòng thử lại sau 1 giờ.';
        } else {
            $msg = 'Nếu email tồn tại trong hệ thống, mã OTP mới đã được gửi.';
        }
    } else {
        $res = pr_verify_otp($conn, $email, trim((string)($_POST['otp'] ?? '')));
        if ($res['ok']) {
            session_regenerate_id(true);
            $_SESSION['pr_reset_id'] = $res['reset_id'];
            header('Location: datlaimatkhau.php');
            exit;
        }
        $err = $res['msg'];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhập mã OTP - Kaido</title>
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
    <h2>Nhập mã OTP</h2>
    <p class="sub-note">Nếu email <b><?= pr_h(pr_mask_email($email)) ?></b> có tài khoản, mã gồm 6 chữ số đã được gửi đến đó (hiệu lực <?= PR_OTP_TTL_MIN ?> phút). Hãy kiểm tra cả mục Spam.</p>
    <?php if ($msg !== ''): ?><div class="msg ok"><?= pr_h($msg) ?></div><?php endif; ?>
    <?php if ($err !== ''): ?><div class="msg err"><?= pr_h($err) ?></div><?php endif; ?>
    <form method="POST" autocomplete="off">
        <?= pr_csrf_field() ?>
        <div class="input-group">
            <label>Mã OTP</label>
            <input type="text" name="otp" class="otp-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="••••••" required autofocus>
        </div>
        <button type="submit">Xác nhận</button>
    </form>
    <form method="POST" style="margin-top:12px">
        <?= pr_csrf_field() ?>
        <input type="hidden" name="action" value="resend">
        <p>Chưa nhận được mã? <button type="submit" class="linkbtn">Gửi lại mã</button></p>
    </form>
    <p><a href="quenmatkhau.php">← Dùng email khác</a></p>
</div>
</body>
<script src="../js/slide.js"></script>
</html>
