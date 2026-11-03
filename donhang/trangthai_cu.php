<?php

// Mã trạng thái dùng trực tiếp giá trị enum của cột orders.status
// pending | confirmed | shipping | completed | cancelled
$DS_TRANG_THAI = [
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đang chuẩn bị hàng',
    'shipping' => 'Đang giao hàng',
    'completed' => 'Đã giao thành công',
];

function tenTrangThai(string $ma): string
{
    global $DS_TRANG_THAI;
    return $ma === 'cancelled' ? 'Đã hủy' : ($DS_TRANG_THAI[$ma] ?? 'Không xác định');
}
function mauTrangThai(string $ma): string
{
    $mau = [
        'pending' => '#b97700', 'confirmed' => '#1764bd',
        'shipping' => '#7544af', 'completed' => '#17834a', 'cancelled' => '#bd3434'
    ];
    return $mau[$ma] ?? '#555555';
}
function tien($n): string
{
    return number_format((float)$n, 0, ',', '.') . ' đ';
}
function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function maDon(int $id): string
{
    return 'KD' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
}
function dhToken(): string
{
    if (empty($_SESSION['dh_csrf'])) {
        $_SESSION['dh_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['dh_csrf'];
}
function dhCheckToken(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['dh_csrf']) || !is_string($token) || $token === ''
        || !hash_equals($_SESSION['dh_csrf'], $token)) {
        http_response_code(403);
        exit('Yêu cầu không hợp lệ (CSRF). Vui lòng tải lại trang.');
    }
}
function dhRequireLogin(string $redirect = '../Taikhoan/dangnhap.html'): int
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . $redirect);
        exit;
    }
    return (int)$_SESSION['user_id'];
}
