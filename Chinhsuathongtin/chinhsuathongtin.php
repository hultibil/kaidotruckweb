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

$stmt = $conn->prepare('SELECT username, email, full_name, phone FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) {
    session_destroy();
    header('Location: ../Taikhoan/dangnhap.php');
    exit;
}

$err = '';
$ok  = '';
$full_name = $user['full_name'];
$email     = $user['email'];
$phone     = (string)($user['phone'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim((string)($_POST['full_name'] ?? ''));
    $email     = trim((string)($_POST['email'] ?? ''));
    $phone     = trim((string)($_POST['phone'] ?? ''));
    $len = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';

    if (!csrf_ok()) {
        $err = 'Phiên làm việc hết hạn, vui lòng thử lại.';
    } elseif ($full_name === '' || $len($full_name) > 150) {
        $err = 'Họ và tên không được để trống (tối đa 150 ký tự).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $err = 'Email không hợp lệ.';
    } elseif ($phone !== '' && !preg_match('/^(\+84|0)[0-9]{8,10}$/', $phone)) {
        $err = 'Số điện thoại không hợp lệ (ví dụ: 0912345678).';
    } else {
        // Email không được trùng với tài khoản khác
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->bind_param('si', $email, $user_id);
        $stmt->execute();
        $dup = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($dup) {
            $err = 'Email này đã được tài khoản khác sử dụng.';
        } else {
            $phoneDb = $phone === '' ? null : $phone;
            try {
                $stmt = $conn->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?');
                $stmt->bind_param('sssi', $full_name, $email, $phoneDb, $user_id);
                $stmt->execute();
                $stmt->close();
                $ok = 'Cập nhật thông tin thành công!';
            } catch (Throwable $e) {
                error_log('chinhsuathongtin: ' . $e->getMessage());
                $err = 'Cập nhật thất bại, vui lòng thử lại.';
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
    <title>Chỉnh sửa thông tin</title>
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
            <h2>Chỉnh sửa thông tin</h2>
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
            <h1>Chỉnh sửa thông tin</h1>
            <p>Cập nhật thông tin cá nhân của bạn</p>
        </div>
        <div class="account-card form-card">
            <?php if ($ok !== ''): ?><div class="form-msg ok"><?= h($ok) ?></div><?php endif; ?>
            <?php if ($err !== ''): ?><div class="form-msg err"><?= h($err) ?></div><?php endif; ?>
            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['acc_csrf']) ?>">
                <div class="input-group">
                    <label>Tên đăng nhập</label>
                    <input type="text" value="<?= h($user['username']) ?>" disabled>
                    <div class="hint">Tên đăng nhập không thể thay đổi.</div>
                </div>
                <div class="input-group">
                    <label>Họ và tên</label>
                    <input type="text" name="full_name" maxlength="150" value="<?= h($full_name) ?>" required>
                </div>
                <div class="input-group">
                    <label>Email</label>
                    <input type="email" name="email" maxlength="150" value="<?= h($email) ?>" required>
                    <div class="hint">Email dùng để nhận mã OTP khi quên mật khẩu.</div>
                </div>
                <div class="input-group">
                    <label>Số điện thoại</label>
                    <input type="tel" name="phone" maxlength="12" value="<?= h($phone) ?>" placeholder="Ví dụ: 0912345678">
                </div>
                <button type="submit">Lưu thay đổi</button>
            </form>
        </div>
        <div class="account-footer">
            <a href="../Taikhoan/taikhoan.php">← Về trang tài khoản</a>
            <a href="../Doimk/doimk.php">Đổi mật khẩu</a>
        </div>
    </div>
</div><!-- glassy-content -->
</body>
</html>
