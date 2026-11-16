# Chức năng gửi phản hồi đến hệ thống

## Thành phần

- `index.php`: trang khách hàng gửi lỗi hoặc góp ý.
- `admin.php`: trang quản trị xem và cập nhật tiến độ xử lý.
- `schema.sql`: tệp tạo bảng `system_feedback`.
- `style.css`: giao diện độc lập, tự thích ứng sáng/tối.

## Cài đặt

1. Chép thư mục `phan-hoi` vào thư mục gốc dự án Kaido.
2. Mở phpMyAdmin, chọn cơ sở dữ liệu `kaido`, rồi nhập tệp `schema.sql`.
3. Mở `http://localhost/kaido/phan-hoi/index.php` để gửi phản hồi.
4. Đăng nhập tài khoản quản trị và mở `http://localhost/kaido/phan-hoi/admin.php` để xem, phân loại và cập nhật tiến độ.

Mục này dùng kết nối có sẵn trong `db.php` và không sửa dữ liệu cũ.
