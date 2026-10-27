<?php
session_start();
require_once "../db.php";

// 1. Phải đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: dangnhap.html");
    exit;
}

// 2. Chỉ nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: xoataikhoan.php");
    exit;
}

// 3. Kiểm tra CSRF token
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $_SESSION['error_message'] = "Lỗi bảo mật (CSRF token không hợp lệ). Vui lòng thử lại!";
    header("Location: xoataikhoan.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$entered_password = $_POST['password'] ?? '';

if ($entered_password === '') {
    $_SESSION['error_message'] = "Vui lòng nhập mật khẩu của bạn.";
    header("Location: xoataikhoan.php");
    exit;
}

try {
    // 4. Lấy mật khẩu đã mã hóa
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $_SESSION['error_message'] = "Không tìm thấy tài khoản trong hệ thống.";
        header("Location: xoataikhoan.php");
        exit;
    }

    // 5. Kiểm tra mật khẩu
    if (!password_verify($entered_password, $user['password'])) {
        $_SESSION['error_message'] = "Mật khẩu bạn nhập không chính xác!";
        header("Location: xoataikhoan.php");
        exit;
    }

    // 6. Xóa tài khoản
    $del = $conn->prepare("DELETE FROM users WHERE id = ?");
    $del->bind_param("i", $user_id);
    $del->execute();

} catch (mysqli_sql_exception $e) {
    $_SESSION['error_message'] = "Không thể xóa tài khoản (có thể còn dữ liệu liên quan như đơn hàng). Vui lòng liên hệ quản trị viên.";
    header("Location: xoataikhoan.php");
    exit;
}

// 7. Hủy session rồi về trang đăng nhập
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header("Location: dangnhap.html?deleted=success");
exit;