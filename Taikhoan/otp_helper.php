<?php
/**
 * otp_helper.php - Hàm dùng chung cho tính năng "Quên mật khẩu" bằng OTP qua Gmail.
 * Được require bởi quenmatkhau.php, xacnhanotp.php, datlaimatkhau.php.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const PR_OTP_TTL_MIN     = 10;  // OTP sống 10 phút
const PR_MAX_ATTEMPTS    = 5;   // nhập sai tối đa 5 lần
const PR_COOLDOWN_SEC    = 60;  // gửi lại cách nhau tối thiểu 60 giây
const PR_MAX_PER_HOUR    = 5;   // tối đa 5 mã/giờ cho mỗi tài khoản
const PR_VERIFIED_TTL_MIN = 10; // sau khi nhập đúng OTP, có 10 phút để đặt mật khẩu mới

function pr_h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function pr_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/mail_config.php';
    }
    return $cfg;
}

/* ---------- CSRF ---------- */
function pr_csrf_field(): string
{
    if (empty($_SESSION['pr_csrf'])) {
        $_SESSION['pr_csrf'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf" value="' . pr_h($_SESSION['pr_csrf']) . '">';
}

function pr_csrf_ok(): bool
{
    return isset($_SESSION['pr_csrf'], $_POST['csrf'])
        && is_string($_POST['csrf'])
        && hash_equals($_SESSION['pr_csrf'], $_POST['csrf']);
}

/* ---------- OTP ---------- */
function pr_make_otp(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function pr_hash_otp(int $resetId, string $otp): string
{
    return hash_hmac('sha256', $resetId . ':' . $otp, pr_config()['otp_secret']);
}

/* ---------- Gửi mail ---------- */
function pr_load_phpmailer(): bool
{
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return true;
    }
    $root = dirname(__DIR__);
    $candidates = [
        $root . '/vendor/autoload.php',                 // cài bằng Composer
        __DIR__ . '/phpmailer/src/PHPMailer.php',       // chép tay vào Taikhoan/phpmailer/src/
        $root . '/phpmailer/src/PHPMailer.php',         // chép tay vào thư mục gốc web/phpmailer/src/
    ];
    foreach ($candidates as $file) {
        if (!is_file($file)) {
            continue;
        }
        if (substr($file, -12) === 'autoload.php') {
            require_once $file;
        } else {
            $dir = dirname($file);
            require_once $dir . '/Exception.php';
            require_once $dir . '/PHPMailer.php';
            require_once $dir . '/SMTP.php';
        }
        return class_exists('PHPMailer\\PHPMailer\\PHPMailer');
    }
    return false;
}

/** Trả về [true, ''] nếu gửi được, ngược lại [false, 'lý do']. */
function pr_send_otp_mail(string $toEmail, string $toName, string $otp): array
{
    $cfg = pr_config();
    if (!pr_load_phpmailer()) {
        return [false, 'Chưa cài PHPMailer (xem hướng dẫn: composer require phpmailer/phpmailer).'];
    }
    try {
        $m = new PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP();
        $m->Host       = $cfg['smtp_host'];
        $m->Port       = (int)$cfg['smtp_port'];
        $m->SMTPAuth   = true;
        $m->Username   = $cfg['smtp_user'];
        $m->Password   = str_replace(' ', '', $cfg['smtp_app_password']);
        $m->SMTPSecure = $cfg['smtp_secure'] === 'ssl'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $m->CharSet    = 'UTF-8';
        $m->Timeout    = 15;

        $m->setFrom($cfg['smtp_user'], $cfg['ten_nguoi_gui']);
        $m->addAddress($toEmail, $toName);
        $m->isHTML(true);
        $m->Subject = 'Mã OTP đặt lại mật khẩu Kaido: ' . $otp;

        $ttl  = PR_OTP_TTL_MIN;
        $name = pr_h($toName !== '' ? $toName : 'bạn');
        $m->Body = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:24px;border:1px solid #eee;border-radius:12px">'
            . '<h2 style="color:#ff416c;margin-top:0">Kaido - Đặt lại mật khẩu</h2>'
            . '<p>Xin chào ' . $name . ',</p>'
            . '<p>Mã OTP của bạn là:</p>'
            . '<p style="font-size:34px;font-weight:bold;letter-spacing:8px;background:#f5f5f5;padding:14px;text-align:center;border-radius:8px">' . pr_h($otp) . '</p>'
            . '<p>Mã có hiệu lực trong <b>' . $ttl . ' phút</b>. Tuyệt đối không chia sẻ mã này cho ai.</p>'
            . '<p style="color:#888;font-size:13px">Nếu bạn không yêu cầu, hãy bỏ qua email này - mật khẩu của bạn vẫn an toàn.</p></div>';
        $m->AltBody = "Mã OTP đặt lại mật khẩu Kaido: $otp (hiệu lực $ttl phút). Không chia sẻ mã này cho ai.";
        $m->send();
        return [true, ''];
    } catch (Throwable $e) {
        error_log('OTP mail lỗi: ' . $e->getMessage());
        return [false, $e->getMessage()];
    }
}

/* ---------- Tạo & gửi OTP ---------- */
/**
 * Xử lý yêu cầu quên mật khẩu cho một email.
 * Trả về: ['status' => 'sent'|'unknown'|'cooldown'|'limit'|'mail_error', 'detail' => string]
 * Phía giao diện luôn hiển thị thông báo chung (trừ mail_error khi bật hien_loi_gui_mail)
 * để người ngoài không dò được email nào có tài khoản.
 */
function pr_request_otp(mysqli $conn, string $email): array
{
    $stmt = $conn->prepare('SELECT id, full_name, username, email FROM users WHERE email = ? ORDER BY id LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) {
        return ['status' => 'unknown', 'detail' => ''];
    }
    $uid = (int)$user['id'];

    // Giới hạn tần suất (dùng giờ của MySQL cho đồng bộ với cột DATETIME)
    $stmt = $conn->prepare('SELECT TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) AS since_last, SUM(created_at > (NOW() - INTERVAL 1 HOUR)) AS c_hour FROM password_resets WHERE user_id = ?');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $lim = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($lim['since_last'] !== null && (int)$lim['since_last'] < PR_COOLDOWN_SEC) {
        return ['status' => 'cooldown', 'detail' => (string)(PR_COOLDOWN_SEC - (int)$lim['since_last'])];
    }
    if ((int)$lim['c_hour'] >= PR_MAX_PER_HOUR) {
        return ['status' => 'limit', 'detail' => ''];
    }

    $otp = pr_make_otp();
    $conn->begin_transaction();
    try {
        // Mã cũ chưa dùng của tài khoản này bị vô hiệu
        $stmt = $conn->prepare('UPDATE password_resets SET expires_at = NOW() WHERE user_id = ? AND verified_at IS NULL AND expires_at > NOW()');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->close();

        $ttl  = PR_OTP_TTL_MIN;
        $stmt = $conn->prepare('INSERT INTO password_resets (user_id, otp_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL ? MINUTE)');
        $placeholder = 'pending';
        $stmt->bind_param('isi', $uid, $placeholder, $ttl);
        $stmt->execute();
        $resetId = (int)$stmt->insert_id;
        $stmt->close();

        $hash = pr_hash_otp($resetId, $otp);
        $stmt = $conn->prepare('UPDATE password_resets SET otp_hash = ? WHERE id = ?');
        $stmt->bind_param('si', $hash, $resetId);
        $stmt->execute();
        $stmt->close();

        // Dọn bản ghi cũ hơn 1 ngày
        $conn->query('DELETE FROM password_resets WHERE created_at < (NOW() - INTERVAL 1 DAY)');
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('pr_request_otp: ' . $e->getMessage());
        return ['status' => 'mail_error', 'detail' => 'Lỗi cơ sở dữ liệu (đã chạy file password_resets.sql chưa?).'];
    }

    [$ok, $err] = pr_send_otp_mail($user['email'], (string)$user['full_name'], $otp);
    if (!$ok) {
        // Gửi lỗi thì hủy mã vừa tạo để không bị tính vào giới hạn
        $stmt = $conn->prepare('DELETE FROM password_resets WHERE id = ?');
        $stmt->bind_param('i', $resetId);
        $stmt->execute();
        $stmt->close();
        return ['status' => 'mail_error', 'detail' => $err];
    }
    return ['status' => 'sent', 'detail' => ''];
}

/**
 * Kiểm tra OTP người dùng nhập cho email đã lưu trong session.
 * Trả về ['ok'=>bool, 'msg'=>string, 'reset_id'=>int]
 */
function pr_verify_otp(mysqli $conn, string $email, string $otp): array
{
    $fail = ['ok' => false, 'msg' => 'Mã OTP không đúng hoặc đã hết hạn.', 'reset_id' => 0];
    if (!preg_match('/^\d{6}$/', $otp)) {
        $fail['msg'] = 'Mã OTP gồm đúng 6 chữ số.';
        return $fail;
    }

    $stmt = $conn->prepare(
        'SELECT r.id, r.otp_hash FROM password_resets r JOIN users u ON u.id = r.user_id
         WHERE u.email = ? AND r.verified_at IS NULL AND r.expires_at > NOW() AND r.attempts < ?
         ORDER BY r.id DESC LIMIT 1'
    );
    $max = PR_MAX_ATTEMPTS;
    $stmt->bind_param('si', $email, $max);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        $fail['msg'] = 'Mã OTP không đúng, đã hết hạn hoặc đã nhập sai quá ' . PR_MAX_ATTEMPTS . ' lần. Vui lòng gửi lại mã mới.';
        return $fail;
    }
    $rid = (int)$row['id'];

    // Tăng số lần thử NGUYÊN TỬ trước khi so sánh (chống thử song song nhiều request)
    $stmt = $conn->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE id = ? AND attempts < ? AND verified_at IS NULL AND expires_at > NOW()');
    $stmt->bind_param('ii', $rid, $max);
    $stmt->execute();
    $claimed = $stmt->affected_rows === 1;
    $stmt->close();
    if (!$claimed) {
        return $fail;
    }

    if (!hash_equals($row['otp_hash'], pr_hash_otp($rid, $otp))) {
        return $fail;
    }

    $stmt = $conn->prepare('UPDATE password_resets SET verified_at = NOW() WHERE id = ? AND verified_at IS NULL');
    $stmt->bind_param('i', $rid);
    $stmt->execute();
    $done = $stmt->affected_rows === 1;
    $stmt->close();
    return $done ? ['ok' => true, 'msg' => '', 'reset_id' => $rid] : $fail;
}

function pr_mask_email(string $email): string
{
    [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
    $keep = mb_substr($name, 0, min(2, max(1, mb_strlen($name) - 1)));
    return $keep . str_repeat('*', max(2, mb_strlen($name) - mb_strlen($keep))) . '@' . $domain;
}

function pr_clear_session(): void
{
    unset($_SESSION['pr_email'], $_SESSION['pr_reset_id']);
}
