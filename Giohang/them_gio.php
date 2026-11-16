<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../donhang/trangthai.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    http_response_code(405); 
    exit('Chỉ chấp nhận POST.'); 
}

dhCheckToken();

$isAjax = isset($_POST['ajax']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

$id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$sl = filter_input(INPUT_POST, 'so_luong', FILTER_VALIDATE_INT);

if (!$id || $id < 1 || !$sl || $sl < 1 || $sl > 99) { 
    http_response_code(400); 
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Sản phẩm/số lượng không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    exit('Sản phẩm/số lượng không hợp lệ.'); 
}

$stmt = $conn->prepare('SELECT id, stock_qty, status FROM products WHERE id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$sp = $stmt->get_result()->fetch_assoc();

if (!$sp || $sp['status'] !== 'active' || (int)$sp['stock_qty'] < $sl) {
    http_response_code(400); 
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Sản phẩm đã hết hàng hoặc không tồn tại.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    exit('Sản phẩm đã hết hàng hoặc không tồn tại.');
}

$cu = (int)($_SESSION['cart'][$id] ?? 0);
if ($cu + $sl > (int)$sp['stock_qty']) { 
    http_response_code(400); 
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Số lượng thêm vượt quá tồn kho.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    exit('Số lượng thêm vượt quá tồn kho.'); 
}

$_SESSION['cart'][$id] = $cu + $sl;

// Phản hồi JSON đồng bộ với xuly_yeuthich.php
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true, 
        'message' => 'Đã thêm sản phẩm vào giỏ hàng thành công!',
        'total_items' => array_sum($_SESSION['cart'])
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Location: giohang.php');
exit;