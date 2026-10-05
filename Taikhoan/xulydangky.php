
<?php

require_once "../db.php";

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';


// Kiểm tra dữ liệu
if ($username === '' || $email === '' || $password === '') {
    die("Vui lòng nhập đầy đủ thông tin.");
}


// Kiểm tra mật khẩu
if ($password !== $confirm_password) {
    die("Mật khẩu nhập lại không khớp.");
}


// Kiểm tra tài khoản đã tồn tại
$sql_check = "SELECT id FROM users WHERE username = ? OR email = ?";

$stmt = $conn->prepare($sql_check);

$stmt->bind_param("ss", $username, $email);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows > 0) {
    die("Tên đăng nhập hoặc email đã tồn tại.");
}


// Mã hóa mật khẩu
$password_hash = password_hash($password, PASSWORD_DEFAULT);


// Thêm tài khoản
$sql = "INSERT INTO users
        (username, email, password, role)
        VALUES (?, ?, ?, 'user')";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sss",
    $username,
    $email,
    $password_hash
);


if ($stmt->execute()) {

    echo "
    <script>
        alert('Đăng ký thành công!');
        window.location.href = 'dangnhap.html';
    </script>
    ";

} else {

    echo "Đăng ký thất bại: " . $conn->error;

}

?>

