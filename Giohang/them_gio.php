<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../donhang/trangthai.php';

// Kiểm tra phương thức POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Chỉ chấp nhận phương thức POST.']);
        exit;
    }
    http_response_code(405);
    exit('Chỉ chấp nhận POST.');
}

dhCheckToken();

$id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$sl = filter_input(INPUT_POST, 'so_luong', FILTER_VALIDATE_INT);

// Xác định có phải request AJAX hay không
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$id || $id < 1 || !$sl || $sl < 1 || $sl > 99) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Sản phẩm hoặc số lượng không hợp lệ.']);
        exit;
    }
    http_response_code(400); 
    exit('Sản phẩm/số lượng không hợp lệ.');
}

$stmt = $conn->prepare('SELECT id, stock_qty, status FROM products WHERE id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$sp = $stmt->get_result()->fetch_assoc();

if (!$sp || $sp['status'] !== 'active' || (int)$sp['stock_qty'] < $sl) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Sản phẩm đã hết hàng hoặc không tồn tại.']);
        exit;
    }
    http_response_code(400); 
    exit('Sản phẩm đã hết hàng hoặc không tồn tại.');
}

$cu = (int)($_SESSION['cart'][$id] ?? 0);
if ($cu + $sl > (int)$sp['stock_qty']) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Số lượng thêm vượt quá tồn kho hiện có.']);
        exit;
    }
    http_response_code(400); 
    exit('Số lượng thêm vượt quá tồn kho.');
}

// Cập nhật giỏ hàng trong session
$_SESSION['cart'][$id] = $cu + $sl;

// Nếu là AJAX, trả về JSON thành công
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success',
        'message' => 'Đã thêm sản phẩm vào giỏ hàng thành công!',
        'total_items' => array_sum($_SESSION['cart'])
    ]);
    exit;
}

// Nếu submit thông thường thì chuyển hướng về giỏ hàng
header('Location: giohang.php');
exit;