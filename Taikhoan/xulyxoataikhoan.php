<?php
session_start();

// 1. Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['user_id'])) {
    header("Location: dangnhap.php");
    exit;
}

// 2. Chỉ chấp nhận phương thức POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: xoataikhoan.php");
    exit;
}

// 3. Kiểm tra bảo mật CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error_message'] = "Lỗi bảo mật (CSRF token không hợp lệ). Vui lòng thử lại!";
    header("Location: xoataikhoan.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$entered_password = $_POST['password'] ?? '';

if (empty($entered_password)) {
    $_SESSION['error_message'] = "Vui lòng nhập mật khẩu của bạn.";
    header("Location: xoataikhoan.php");
    exit;
}

// --- CẤU HÌNH KẾT NỐI DATABASE (Sửa lại cho đúng với nhóm bạn) ---
$host = 'localhost';
$dbname = 'kaido';
$username_db = 'root';
$password_db = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 4. Lấy mật khẩu đã mã hóa (hash) của user này từ Database
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['error_message'] = "Không tìm thấy tài khoản trong hệ thống.";
        header("Location: xoataikhoan.php");
        exit;
    }

    // 5. Kiểm tra mật khẩu người dùng nhập có khớp với mật khẩu mã hóa trong DB không
    if (!password_verify($entered_password, $user['password'])) {
        $_SESSION['error_message'] = "Mật khẩu bạn nhập không chính xác!";
        header("Location: xoataikhoan.php");
        exit;
    }

    // 6. Tiến hành xóa tài khoản khỏi Database
    $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $delete_stmt->execute([$user_id]);

    // 7. Xóa toàn bộ session và chuyển hướng về trang chủ hoặc đăng nhập
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    // Thông báo hoặc chuyển hướng về trang đăng nhập/trang chủ
    // Bạn có thể tạo thông báo dạng flash session bên trang login nếu muốn
    header("Location: dangnhap.html?deleted=success");
    exit;

} catch (PDOException $e) {
    // Xử lý lỗi database nếu có
    $_SESSION['error_message'] = "Lỗi hệ thống cơ sở dữ liệu. Vui lòng thử lại sau!";
    header("Location: xoataikhoan.php");
    exit;
}
?>