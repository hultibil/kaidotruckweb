<?php
function admin_luu_anh(array $file): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Tải ảnh lên thất bại.');
    }
    if (!isset($file['size']) || $file['size'] <= 0 || $file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Ảnh phải có dung lượng từ 1 byte đến 2 MB.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Tệp tải lên không hợp lệ.');
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) {
        throw new RuntimeException('Chỉ cho phép ảnh JPG, PNG, GIF hoặc WEBP.');
    }
    $folder = __DIR__ . '/../image/uploads/';
    if (!is_dir($folder) || !is_writable($folder)) {
        throw new RuntimeException('Không tìm thấy hoặc không có quyền ghi thư mục image/uploads.');
    }
    $filename = 'sp_' . bin2hex(random_bytes(12)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $folder . $filename)) {
        throw new RuntimeException('Không thể lưu hình ảnh sản phẩm.');
    }
    return $filename;
}
