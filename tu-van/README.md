# Chức năng gửi yêu cầu tư vấn

## Thành phần

- `index.php`: trang khách hàng gửi yêu cầu.
- `admin.php`: trang quản trị xem và cập nhật trạng thái.
- `schema.sql`: tệp tạo bảng `consultation_requests`.
- `style.css`: giao diện độc lập, tự thích ứng sáng/tối.

## Cài đặt

1. Chép thư mục `tu-van` vào thư mục gốc dự án Kaido.
2. Mở phpMyAdmin, chọn cơ sở dữ liệu `kaido`, rồi nhập tệp `schema.sql`.
3. Mở `http://localhost/kaido/tu-van/index.php` để khách gửi yêu cầu.
4. Đăng nhập tài khoản quản trị và mở `http://localhost/kaido/tu-van/admin.php` để xử lý.

Mục này dùng kết nối có sẵn trong `db.php` và không sửa dữ liệu cũ.
