<?php
// Đặt tại: sanpham/xuly_yeuthich.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../donhang/trangthai.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Chỉ hỗ trợ POST.'); }
$isAjax = isset($_POST['ajax']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Vui lòng đăng nhập trước khi thêm sản phẩm yêu thích.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    dhRequireLogin();
}
$token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['dh_csrf']) || !is_string($token) || $token === '' || !hash_equals($_SESSION['dh_csrf'], $token)) {
    http_response_code(403);
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Phiên đăng nhập đã hết hạn. Vui lòng tải lại trang.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    exit('Yêu cầu không hợp lệ (CSRF). Vui lòng tải lại trang.');
}
$userId = (int)$_SESSION['user_id'];
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

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'liked' => $action === 'add', 'message' => $action === 'add' ? 'Đã thêm vào yêu thích.' : 'Đã bỏ khỏi yêu thích.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$to = 'shop.php#dautrang';
if ($from === 'detail') $to = 'chitietsanpham.php?id=' . $id;
if ($from === 'favorites') $to = 'yeuthich.php';
header('Location: ' . $to, true, 303);
exit;
