<?php
// Đặt tại: admin/quanly_tuongthich.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/baove.php';                  // kiểm tra quyền admin của bạn
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../donhang/trangthai.php';   // h(), dhToken(), dhCheckToken()

$thongBao = ''; $loi = '';
// SỬA: status='active'; dùng dhToken/dhCheckToken (không phụ thuộc hàm admin_csrf_* của người kia)
$products = $conn->query("SELECT id,product_name FROM products WHERE status='active' ORDER BY product_name,id")->fetch_all(MYSQLI_ASSOC);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dhCheckToken();
    $a = filter_input(INPUT_POST, 'a', FILTER_VALIDATE_INT) ?: 0;
    $b = filter_input(INPUT_POST, 'b', FILTER_VALIDATE_INT) ?: 0;
    $verdict = (string)($_POST['ket_qua'] ?? '');
    $note = trim((string)($_POST['ghi_chu'] ?? ''));
    if (!$a || !$b || $a === $b || !in_array($verdict, ['tuong_thich', 'khong_tuong_thich', 'chua_xac_dinh'], true) || mb_strlen($note) > 500) {
        $loi = 'Thông tin không hợp lệ.';
    } else {
        try {
            $st = $conn->prepare('SELECT COUNT(*) AS n FROM products WHERE id IN (?,?)');
            $st->bind_param('ii', $a, $b); $st->execute(); $valid = (int)$st->get_result()->fetch_assoc()['n'] === 2; $st->close();
            if (!$valid) throw new RuntimeException('Sản phẩm không tồn tại.');
            $lo = min($a, $b); $hi = max($a, $b);
            $st = $conn->prepare('INSERT INTO tuong_thich_linh_kien (product_a_id,product_b_id,ket_qua,ghi_chu) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE ket_qua=VALUES(ket_qua),ghi_chu=VALUES(ghi_chu),updated_at=CURRENT_TIMESTAMP');
            $st->bind_param('iiss', $lo, $hi, $verdict, $note); $st->execute(); $st->close();
            header('Location: quanly_tuongthich.php?ok=1'); exit;
        } catch (Throwable $e) { error_log($e->getMessage()); $loi = 'Không lưu được quy tắc tương thích.'; }
    }
}
if (isset($_GET['ok'])) $thongBao = 'Đã cập nhật kết quả tương thích.';
$rules = $conn->query('SELECT t.id,t.ket_qua,t.ghi_chu,t.updated_at,p1.product_name AS a,p2.product_name AS b FROM tuong_thich_linh_kien t LEFT JOIN products p1 ON p1.id=t.product_a_id LEFT JOIN products p2 ON p2.id=t.product_b_id ORDER BY t.updated_at DESC LIMIT 100')->fetch_all(MYSQLI_ASSOC);
$nhan = ['tuong_thich' => 'Tương thích', 'khong_tuong_thich' => 'Không tương thích', 'chua_xac_dinh' => 'Chưa xác định'];
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Quản lý tương thích - Kaido</title><link rel="stylesheet" href="../css/kaido-muasam.css"><style>body{margin:0;background:#151921;color:#fff;font-family:Arial,sans-serif}</style></head><body>
<nav class="kaido-shop-nav"><a href="admin.php">Quản trị</a><a href="capnhat_trangthai.php">Đơn hàng</a><a href="../sanpham/tuongthich.php">Xem phía khách</a><a href="../index.php">Trang chủ</a></nav>
<main class="kaido-main"><h1>Quản lý tương thích linh kiện</h1>
<?php if ($thongBao): ?><p class="kaido-msg kaido-ok"><?= h($thongBao) ?></p><?php endif; ?>
<?php if ($loi): ?><p class="kaido-msg kaido-bad"><?= h($loi) ?></p><?php endif; ?>
<form method="post" class="kaido-panel kaido-form">
<input type="hidden" name="csrf_token" value="<?= h(dhToken()) ?>">
<label>Linh kiện thứ nhất<select name="a" required><option value="">Chọn sản phẩm</option><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= h($p['product_name']) ?></option><?php endforeach; ?></select></label>
<label>Linh kiện thứ hai<select name="b" required><option value="">Chọn sản phẩm</option><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= h($p['product_name']) ?></option><?php endforeach; ?></select></label>
<label>Kết luận<select name="ket_qua" required><option value="chua_xac_dinh">Chưa xác định</option><option value="tuong_thich">Tương thích</option><option value="khong_tuong_thich">Không tương thích</option></select></label>
<label>Điều kiện và giải thích<textarea name="ghi_chu" rows="3" maxlength="500" placeholder="Ví dụ: Kiểm tra điện áp, dòng tải, giao tiếp, chân cắm và điều kiện sử dụng."></textarea></label>
<button class="kaido-btn" type="submit">Lưu kết quả</button></form>
<section class="kaido-panel"><h2>Các cặp đã thiết lập</h2><div style="overflow-x:auto"><table class="kaido-table"><thead><tr><th>Linh kiện A</th><th>Linh kiện B</th><th>Kết luận</th><th>Ghi chú</th><th>Cập nhật</th></tr></thead><tbody>
<?php foreach ($rules as $r): ?><tr><td><?= h($r['a'] ?? 'Đã xóa') ?></td><td><?= h($r['b'] ?? 'Đã xóa') ?></td><td><?= h($nhan[$r['ket_qua']] ?? $r['ket_qua']) ?></td><td><?= h($r['ghi_chu']) ?></td><td><?= h($r['updated_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></section></main></body></html>
