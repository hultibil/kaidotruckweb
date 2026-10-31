
<?php

session_start(); // Bắt đầu session để quản lý đăng nhập

session_unset(); // Xóa tất cả các biến session

session_destroy();  // Hủy session

header("Location: ../index.php");  // Chuyển hướng về trang chủ sau khi đăng xuất

exit; // Kết thúc script để đảm bảo không có mã nào chạy sau khi chuyển hướng

?>

