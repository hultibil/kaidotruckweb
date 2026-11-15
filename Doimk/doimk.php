<?php
session_start();
require_once __DIR__ . '/../db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../Taikhoan/dangnhap.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
if (empty($_SESSION['acc_csrf'])) { $_SESSION['acc_csrf'] = bin2hex(random_bytes(32)); }
function csrf_ok() {
    return isset($_POST['csrf']) && is_string($_POST['csrf']) && hash_equals($_SESSION['acc_csrf'], $_POST['csrf']);
}

const MAX_FAIL = 5;     // sai mật khẩu cũ tối đa 5 lần
const LOCK_SEC = 300;   // rồi khóa 5 phút

$err = '';
$ok  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = (string)($_POST['old_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $cfm = (string)($_POST['confirm_password'] ?? '');
    $lockUntil = (int)($_SESSION['pw_lock_until'] ?? 0);

    if (!csrf_ok()) {
        $err = 'Phiên làm việc hết hạn, vui lòng thử lại.';
    } elseif ($lockUntil > time()) {
        $err = 'Đổi mật khẩu thất bại: bạn nhập sai quá nhiều lần. Thử lại sau ' . ceil(($lockUntil - time()) / 60) . ' phút.';
    } elseif ($old === '' || $new === '' || $cfm === '') {
        $err = 'Vui lòng nhập đầy đủ các ô.';
    } elseif (strlen($new) < 8) {
        $err = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
    } elseif (strlen($new) > 72) {
        $err = 'Mật khẩu mới tối đa 72 ký tự.';
    } elseif ($new !== $cfm) {
        $err = 'Mật khẩu mới và ô nhập lại không khớp.';
    } elseif ($new === $old) {
        $err = 'Mật khẩu mới phải khác mật khẩu cũ.';
    } else {
        $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($old, $row['password'])) {
            $_SESSION['pw_fail'] = (int)($_SESSION['pw_fail'] ?? 0) + 1;
            if ($_SESSION['pw_fail'] >= MAX_FAIL) {
                $_SESSION['pw_lock_until'] = time() + LOCK_SEC;
                $_SESSION['pw_fail'] = 0;
            }
            $err = 'Đổi mật khẩu thất bại: mật khẩu cũ không đúng.';
        } else {
            try {
                $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                $stmt->bind_param('si', $hash, $user_id);
                $stmt->execute();
                $stmt->close();
                unset($_SESSION['pw_fail'], $_SESSION['pw_lock_until']);
                session_regenerate_id(true);
                $ok = 'Đổi mật khẩu thành công!';
            } catch (Throwable $e) {
                error_log('doimk: ' . $e->getMessage());
                $err = 'Đổi mật khẩu thất bại, vui lòng thử lại.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đổi mật khẩu</title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="../css/dndk.css">
    <link rel="stylesheet" href="../css/glassy.css">
    <script src="../js/glassy-theme.js"></script>
    <style>
        .glassy-page .glassy-content .form-card{max-width:560px;margin:0 auto 18px;padding:22px}
        .form-card .input-group{margin-bottom:14px;text-align:left}
        .form-card .input-group label{display:block;margin-bottom:6px;font-weight:700;font-size:14px}
        .form-card .input-group input{width:100%;box-sizing:border-box;padding:11px 13px;border:1px solid var(--field-border);border-radius:12px;font:inherit}
        .form-card .input-group input:disabled{opacity:.65;cursor:not-allowed}
        .form-card .hint{font-size:12px;margin-top:5px;color:var(--muted)!important}
        .form-card button[type="submit"]{width:100%;padding:12px;border:0;border-radius:12px;font:inherit;font-weight:700;cursor:pointer;color:#fff!important;background:var(--primary-gradient)!important}
        .form-card button[type="submit"]:hover{filter:brightness(1.08)}
        .form-card a{color:var(--accent);font-weight:600}
        .form-msg{padding:10px 14px;border-radius:12px;margin-bottom:14px;font-size:14px;line-height:1.45;font-weight:600}
        .form-msg.err{background:rgba(239,68,68,.14);border:1px solid rgba(239,68,68,.45);color:var(--danger)!important}
        .form-msg.ok{background:rgba(16,185,129,.14);border:1px solid rgba(16,185,129,.45);color:var(--success)!important}
        .form-extra{margin:14px 0 0;text-align:center}
        .glassy-page .account-container .account-footer{display:flex;flex-wrap:wrap;gap:8px 22px;justify-content:center}
        .glassy-page .account-container .account-footer a{color:var(--accent)}
    </style>
</head>
<body class="glassy-page account-page">
<?php $glassyBase = '../'; require __DIR__ . '/../layout/glassy-sidebar.php'; ?>
<div id="glassy-content" class="glassy-content" tabindex="-1">
    <div class="header" id="mainHeader">
        <div class="header-left">
            <img class="logo" src="../image/kaidologo.jpg">
            <h2>Đổi mật khẩu</h2>
        </div>
        <div class="header-right">
            <div class="user-header-badge">
                <span>👋 Hi, <strong><?= h($_SESSION['username'] ?? '') ?></strong></span>
            </div>
        </div>
    </div>

    <div id="dautrang">
        <div class="nav-container">
            <div class="nav-scroll-wrapper">
                <div class="nut0"><a href="../index.php">Trang chủ</a></div>
                <div class="nut1"><a href="../khac/khac.php">Dự án khác</a></div>
                <div class="nut2"><a href="../hoithem.php">Trò chuyện</a></div>
                <div class="nut4chon"><a href="../tienich.php">Tài khoản/Mua hàng</a></div>
                <div class="nut5"><a href="../kythuat/details.php">Chi tiết kỹ thuật</a></div>
                <div class="nut6"><a href="../Blog/blog.php">Tin tức</a></div>
                <div class="nut3"><a href="../gamemini.php">Game mini</a></div>
            </div>
        </div>
    </div>

    <div class="account-container">
        <div class="account-header">
            <h1>Đổi mật khẩu</h1>
            <p>Nhập mật khẩu cũ để đặt mật khẩu mới</p>
        </div>
        <div class="account-card form-card">
            <?php if ($ok !== ''): ?><div class="form-msg ok"><?= h($ok) ?></div><?php endif; ?>
            <?php if ($err !== ''): ?><div class="form-msg err"><?= h($err) ?></div><?php endif; ?>
            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['acc_csrf']) ?>">
                <div class="input-group">
                    <label>Mật khẩu cũ</label>
                    <input type="password" name="old_password" placeholder="Nhập mật khẩu hiện tại" required>
                </div>
                <div class="input-group">
                    <label>Mật khẩu mới</label>
                    <input type="password" name="new_password" placeholder="Ít nhất 8 ký tự" minlength="8" maxlength="72" required>
                </div>
                <div class="input-group">
                    <label>Nhập lại mật khẩu mới</label>
                    <input type="password" name="confirm_password" placeholder="Nhập lại mật khẩu mới" minlength="8" maxlength="72" required>
                </div>
                <button type="submit">Đổi mật khẩu</button>
            </form>
            <p class="form-extra"><a href="../Taikhoan/quenmatkhau.php">Quên mật khẩu?</a></p>
        </div>
        <div class="account-footer">
            <a href="../Taikhoan/taikhoan.php">← Về trang tài khoản</a>
            <a href="../Chinhsuathongtin/chinhsuathongtin.php">Chỉnh sửa thông tin</a>
        </div>
    </div>
</div><!-- glassy-content -->
</body>
</html>
