<?php
// Đặt tại: ai/chat_api.php   (endpoint JSON cho chatbox AI ở hoithem.php)
// Khách vãng lai vẫn chat được (giống NagaMC); đăng nhập thì lịch sử gắn theo tài khoản.
if (session_status() === PHP_SESSION_NONE) session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/ai_service.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function ai_out(array $d, int $code = 200): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

$sid = session_id();
$uid = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$after = (int)($_SESSION['ai_clear_after'] ?? 0);
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'history') {
    $out = [];
    foreach (kaido_ai_history($conn, $sid, $uid, 20, $after) as $r) {
        $ids = json_decode((string)$r['recommended_product_ids'], true);
        $out[] = [
            'q' => $r['user_message'], 'a' => $r['ai_response'], 'status' => $r['response_status'],
            'products' => is_array($ids) ? kaido_ai_cards_by_ids($conn, $ids) : [],
        ];
    }
    ai_out(['ok' => true, 'items' => $out]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { ai_out(['ok' => false, 'message' => 'Chỉ hỗ trợ POST.'], 405); }

// CSRF
$tok = (string)($_POST['csrf'] ?? '');
if (empty($_SESSION['ai_csrf']) || $tok === '' || !hash_equals($_SESSION['ai_csrf'], $tok)) {
    ai_out(['ok' => false, 'message' => 'Phiên đã hết hạn, hãy tải lại trang.'], 403);
}

if ($action === 'clear') {
    // Không xóa log (admin còn cần xem) - chỉ ẩn lịch sử cũ khỏi chatbox của khách
    $max = (int)($conn->query('SELECT COALESCE(MAX(id),0) AS m FROM ai_chat_logs')->fetch_assoc()['m'] ?? 0);
    $_SESSION['ai_clear_after'] = $max;
    ai_out(['ok' => true]);
}

if ($action !== 'send') { ai_out(['ok' => false, 'message' => 'Yêu cầu không hợp lệ.'], 400); }

$msg = trim((string)($_POST['message'] ?? ''));
if ($msg === '') { ai_out(['ok' => false, 'message' => 'Vui lòng nhập câu hỏi.'], 422); }
if (mb_strlen($msg) > 500) { ai_out(['ok' => false, 'message' => 'Câu hỏi quá dài (tối đa 500 ký tự).'], 422); }

// Giới hạn tốc độ để không cạn quota Gemini: 8 câu / phút / phiên
$now = time();
$hits = array_values(array_filter((array)($_SESSION['ai_hits'] ?? []), fn($t) => $now - $t < 60));
if (count($hits) >= 8) { ai_out(['ok' => false, 'message' => 'Bạn hỏi hơi nhanh, đợi một chút rồi thử lại nhé.'], 429); }
$hits[] = $now;
$_SESSION['ai_hits'] = $hits;
session_write_close();   // không giữ khóa session trong lúc chờ Gemini (tránh treo các trang khác)

try {
    $r = kaido_ai_chat($conn, $msg, $sid, $uid, $after);
    if (($_SESSION['role'] ?? '') !== 'admin') { unset($r['error']); }   // khách không thấy chi tiết lỗi
    ai_out(['ok' => true] + $r);
} catch (Throwable $e) {
    error_log('ai/chat_api: ' . $e->getMessage());
    ai_out(['ok' => false, 'message' => 'Không xử lý được câu hỏi, thử lại sau nhé.'], 500);
}
