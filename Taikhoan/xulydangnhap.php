<?php
session_start();
require_once "../db.php";

// Quay lại form đăng nhập kèm thông báo lỗi (dangnhap.php sẽ hiện ngay trên form)
function dangNhapThatBai(string $thongBao, string $username = ''): void
{
    $_SESSION['login_error'] = $thongBao;
    $_SESSION['login_old_user'] = $username;
    header('Location: dangnhap.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dangnhap.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
if ($username === '' || $password === '') {
    dangNhapThatBai('Vui lòng nhập tên đăng nhập và mật khẩu.', $username);
}

// tìm account trong db
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Cùng một thông báo cho "sai tên" và "sai mật khẩu" để không lộ tài khoản nào tồn tại
if (!$user || !password_verify($password, $user['password'])) {
    dangNhapThatBai('Tên đăng nhập hoặc mật khẩu không đúng.', $username);
}

// tạo session rồi lưu
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];

// admin -> trang admin, user -> trang chủ
if ($user['role'] === 'admin') {
    header("Location: ../admin/admin.php");
} else {
    header("Location: ../index.php");
}
exit;
