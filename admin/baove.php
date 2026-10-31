<?php
/**
 * baove.php - "Người gác cổng" của khu quản trị.
 * Nhúng ở DÒNG ĐẦU TIÊN của mọi trang quản trị, trước mọi xử lý và HTML:
 *     require_once __DIR__ . '/baove.php';
 *
 * Quy tắc:
 *  1. Chưa đăng nhập            -> chuyển về trang đăng nhập.
 *  2. Đã đăng nhập, không phải admin -> báo 403, dừng hẳn.
 *  3. Vai trò được kiểm tra lại trong DATABASE ở mỗi lần tải trang,
 *     không tin hoàn toàn vào $_SESSION (đổi role/xóa tài khoản có hiệu lực ngay).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../db.php';

// Không cho trình duyệt lưu cache trang quản trị (bấm Back sau khi đăng xuất sẽ không thấy lại)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// 1. Chưa đăng nhập
if (empty($_SESSION['user_id'])) {
    header('Location: ../Taikhoan/dangnhap.html');
    exit;
}

// 2. Lấy vai trò thật từ database
$__uid = (int)$_SESSION['user_id'];
$__st = $conn->prepare('SELECT username, role FROM users WHERE id = ?');
$__st->bind_param('i', $__uid);
$__st->execute();
$__user = $__st->get_result()->fetch_assoc();
$__st->close();

// Tài khoản đã bị xóa nhưng session còn
if (!$__user) {
    $_SESSION = [];
    session_destroy();
    header('Location: ../Taikhoan/dangnhap.html');
    exit;
}

// 3. Không phải admin
if ($__user['role'] !== 'admin') {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><title>403</title></head>'
       . '<body style="font-family:sans-serif;text-align:center;margin-top:80px">'
       . '<h2>403 - Bạn không có quyền truy cập trang quản trị</h2>'
       . '<p><a href="../index.php">Về trang chủ</a></p></body></html>';
    exit;
}

$admin_username = $__user['username'];

/* ---- CSRF: chống việc kẻ khác lừa admin bấm link/gửi form ngầm ---- */
function admin_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function admin_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Gọi ở đầu mọi xử lý POST (thêm/sửa/xóa) */
function admin_csrf_check(): void
{
    if (
        $_SERVER['REQUEST_METHOD'] !== 'POST' ||
        !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
    ) {
        http_response_code(400);
        exit('Yêu cầu không hợp lệ (sai mã bảo mật). Hãy quay lại trang quản trị và thử lại.');
    }
}

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
