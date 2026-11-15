<?php

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../donhang/trangthai.php';
require_once __DIR__ . '/../donhang/luu_donhang.php';
$userId = dhRequireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Chỉ hỗ trợ POST.'); }
dhCheckToken();
$ten = trim((string)($_POST['ten'] ?? ''));
$sdt = trim((string)($_POST['sdt'] ?? ''));
$diaChi = trim((string)($_POST['dia_chi'] ?? ''));
if ($ten === '' || mb_strlen($ten) > 100 ||
    !preg_match('/^[0-9+ .-]{9,20}$/', $sdt) ||
    $diaChi === '' || mb_strlen($diaChi) > 255) {
    http_response_code(422); exit('Thông tin người nhận chưa hợp lệ.');
}
try {
    $donId = taoDonHang($conn, $userId, $ten, $sdt, $diaChi, $_SESSION['cart'] ?? []);
    unset($_SESSION['cart']);

    unset($_SESSION['dh_csrf']);
    header('Location: ../donhang/theodoi.php?id=' . $donId);
    exit;
} catch (Throwable $e) {
    http_response_code(400);

    error_log('Dat hang that bai: ' . $e->getMessage());
    exit('Đặt hàng thất bại. Hãy kiểm tra giỏ hàng, số lượng tồn kho và thử lại. <a href="giohang.php">Quay lại giỏ</a>');
}
