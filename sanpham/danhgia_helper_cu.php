<?php
/**
 * danhgia_helper.php - hàm dùng chung cho tính năng Đánh giá sản phẩm.
 * Đặt trong thư mục sanpham/. Được require bởi danhgia.php, danhgia_cho.php
 * (và sau này: chitietsanpham.php, trang admin).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const RV_MAX_IMAGES      = 5;                 // tối đa 5 ảnh / đánh giá
const RV_MAX_IMAGE_BYTES = 3 * 1024 * 1024;   // 3MB / ảnh
const RV_MAX_VIDEO_BYTES = 20 * 1024 * 1024;  // 20MB / video (tối đa 1 video)
const RV_COMMENT_MAX     = 1000;

function rv_h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function rv_require_login(): int
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ../Taikhoan/dangnhap.php');
        exit;
    }
    return (int)$_SESSION['user_id'];
}

/* ---------- CSRF ---------- */
function rv_csrf_field(): string
{
    if (empty($_SESSION['rv_csrf'])) {
        $_SESSION['rv_csrf'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf" value="' . rv_h($_SESSION['rv_csrf']) . '">';
}

function rv_csrf_ok(): bool
{
    return isset($_SESSION['rv_csrf'], $_POST['csrf']) && is_string($_POST['csrf'])
        && hash_equals($_SESSION['rv_csrf'], $_POST['csrf']);
}

/* ---------- Đường dẫn file ---------- */
function rv_upload_dir(): string
{
    return dirname(__DIR__) . '/image/uploads/reviews/';
}

/** URL tương đối (từ thư mục sanpham/ hoặc admin/) tới file đã lưu, vd reviews/ab.jpg */
function rv_media_url(string $path): string
{
    return '../image/uploads/' . $path;
}

/* ---------- Kiểm tra file tải lên ---------- */
/**
 * @param string $kind 'image' | 'video'
 * @return array [bool ok, string ext, string lỗi]
 */
function rv_validate_upload(string $tmp, int $size, string $kind): array
{
    if ($size <= 0) {
        return [false, '', 'File rỗng.'];
    }
    $limit = $kind === 'video' ? RV_MAX_VIDEO_BYTES : RV_MAX_IMAGE_BYTES;
    if ($size > $limit) {
        return [false, '', ($kind === 'video' ? 'Video' : 'Ảnh') . ' quá lớn (tối đa ' . ($limit / 1048576) . 'MB).'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
    $map = $kind === 'video'
        ? ['video/mp4' => 'mp4', 'video/webm' => 'webm']
        : ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($map[$mime])) {
        return [false, '', $kind === 'video' ? 'Video chỉ nhận MP4 hoặc WebM.' : 'Ảnh chỉ nhận JPG, PNG hoặc WebP.'];
    }
    if ($kind === 'image' && @getimagesize($tmp) === false) {
        return [false, '', 'File ảnh không hợp lệ.'];
    }
    return [true, $map[$mime], ''];
}

/** Chuyển $_FILES['x'] dạng mảng thành danh sách file, bỏ qua ô trống. */
function rv_normalize_files(?array $f): array
{
    $out = [];
    if (!$f || !isset($f['name'])) {
        return $out;
    }
    if (!is_array($f['name'])) {
        $f = array_map(fn($v) => [$v], $f);
    }
    foreach ($f['name'] as $i => $name) {
        $err = $f['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = ['name' => (string)$name, 'tmp_name' => (string)$f['tmp_name'][$i], 'size' => (int)$f['size'][$i], 'error' => (int)$err];
    }
    return $out;
}

/* ---------- Hiển thị ---------- */
function rv_stars(int $n): string
{
    $n = max(0, min(5, $n));
    return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
}

function rv_product_img(?string $imagePath): string
{
    return '../image/uploads/' . rawurlencode((string)$imagePath);
}
