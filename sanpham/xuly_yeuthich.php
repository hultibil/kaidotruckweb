<?php
// Đặt tại: sanpham/xuly_yeuthich.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../donhang/trangthai.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Chỉ hỗ trợ POST.'); }
dhCheckToken();
$userId = dhRequireLogin();
$id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$action = (string)($_POST['action'] ?? '');
$from = (string)($_POST['return_page'] ?? 'shop');
if (!$id || $id <= 0 || !in_array($action, ['add', 'remove'], true)) { http_response_code(422); exit('Dữ liệu không hợp lệ.'); }

// SỬA: web bạn dùng status 'active'/'inactive' (không có 'hidden')
$st = $conn->prepare("SELECT id FROM products WHERE id=? AND status='active' LIMIT 1");
$st->bind_param('i', $id);
$st->execute();
$exists = (bool)$st->get_result()->fetch_row();
$st->close();
if (!$exists && $action === 'add') { http_response_code(404); exit('Sản phẩm không tồn tại.'); }

if ($action === 'add') {
    $st = $conn->prepare('INSERT IGNORE INTO san_pham_yeu_thich (user_id,product_id) VALUES (?,?)');
} else {
    $st = $conn->prepare('DELETE FROM san_pham_yeu_thich WHERE user_id=? AND product_id=?');
}
$st->bind_param('ii', $userId, $id);
$st->execute();
$st->close();

$to = 'shop.php#dautrang';
if ($from === 'detail') $to = 'chitietsanpham.php?id=' . $id;
if ($from === 'favorites') $to = 'yeuthich.php';
header('Location: ' . $to, true, 303);
exit;
