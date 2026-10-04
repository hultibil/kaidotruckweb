
<?php

session_start();

require_once "db.php";


$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';


if ($username === '' || $password === '') {
    die("Vui lòng nhập tên đăng nhập và mật khẩu.");
}


// Tìm tài khoản
$sql = "SELECT * FROM users WHERE username = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param("s", $username);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    die("Tên đăng nhập hoặc mật khẩu không đúng.");

}


$user = $result->fetch_assoc();


// Kiểm tra mật khẩu
if (!password_verify($password, $user['password'])) {

    die("Tên đăng nhập hoặc mật khẩu không đúng.");

}


// Tạo session
$_SESSION['user_id'] = $user['id'];

$_SESSION['username'] = $user['username'];

$_SESSION['role'] = $user['role'];


// Đăng nhập thành công
if ($user['role'] === 'admin') {

    header("Location: quantri.php");

} else {

    header("Location: index.php");

}

exit;

?>
