<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/otp_helper.php';

$rid = (int)($_SESSION['pr_reset_id'] ?? 0);
if ($rid < 1) {
    header('Location: quenmatkhau.php');
    exit;
}

// Phiên đặt lại phải còn hiệu lực (đã nhập đúng OTP trong vòng PR_VERIFIED_TTL_MIN phút)
$stmt = $conn->prepare('SELECT user_id FROM password_resets WHERE id = ? AND verified_at IS NOT NULL AND verified_at > (NOW() - INTERVAL ? MINUTE)');
$ttl = PR_VERIFIED_TTL_MIN;
$stmt->bind_param('ii', $rid, $ttl);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
    pr_clear_session();
    header('Location: quenmatkhau.php');
    exit;
}
$uid = (int)$row['user_id'];

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p1 = (string)($_POST['password'] ?? '');
    $p2 = (string)($_POST['password2'] ?? '');
    if (!pr_csrf_ok()) {
        $err = 'Phiên làm việc hết hạn, vui lòng thử lại.';
    } elseif (strlen($p1) < 8) {
        $err = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } elseif (strlen($p1) > 72) {
        $err = 'Mật khẩu tối đa 72 ký tự.';
    } elseif ($p1 !== $p2) {
        $err = 'Hai mật khẩu không khớp.';
    } else {
        $hash = password_hash($p1, PASSWORD_BCRYPT, ['cost' => 12]);
        $conn->begin_transaction();
        try {
            // Chỉ cho dùng 1 lần: khóa dòng, xác nhận vẫn còn hiệu lực rồi đổi mật khẩu
            $stmt = $conn->prepare('SELECT id FROM password_resets WHERE id = ? AND verified_at IS NOT NULL AND verified_at > (NOW() - INTERVAL ? MINUTE) FOR UPDATE');
            $stmt->bind_param('ii', $rid, $ttl);
            $stmt->execute();
            $still = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$still) {
                throw new RuntimeException('expired');
            }
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->bind_param('si', $hash, $uid);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare('DELETE FROM password_resets WHERE user_id = ?');
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
            pr_clear_session();
            session_regenerate_id(true);
            header('Location: dangnhap.php?reset=ok');
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            if ($e->getMessage() === 'expired') {
                pr_clear_session();
                header('Location: quenmatkhau.php');
                exit;
            }
            error_log('datlaimatkhau: ' . $e->getMessage());
            $err = 'Không thể đổi mật khẩu, vui lòng thử lại.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt mật khẩu mới - Kaido</title>
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
    <h2>Đặt mật khẩu mới</h2>
    <p class="sub-note">Xác thực thành công. Hãy nhập mật khẩu mới (tối thiểu 8 ký tự).</p>
    <?php if ($err !== ''): ?><div class="msg err"><?= pr_h($err) ?></div><?php endif; ?>
    <form method="POST" autocomplete="off">
        <?= pr_csrf_field() ?>
        <div class="input-group">
            <label>Mật khẩu mới</label>
            <input type="password" name="password" placeholder="Ít nhất 8 ký tự" minlength="8" maxlength="72" required autofocus>
        </div>
        <div class="input-group">
            <label>Nhập lại mật khẩu mới</label>
            <input type="password" name="password2" placeholder="Nhập lại mật khẩu" minlength="8" maxlength="72" required>
        </div>
        <button type="submit">Đổi mật khẩu</button>
    </form>
</div>
</body>
<script src="../js/slide.js"></script>
</html>
