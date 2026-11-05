<?php
// Đặt tại: admin/capnhat_trangthai.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/baove.php';                  // kiểm tra quyền admin của bạn
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../donhang/trangthai.php';

$thongBao = '';
$loi = '';

// SỬA: dùng mã trạng thái của web bạn (enum orders.status)
// pending | confirmed | shipping | completed | cancelled
$duocPhep = [
    'pending'   => ['confirmed', 'cancelled'],
    'confirmed' => ['shipping', 'cancelled'],
    'shipping'  => ['completed'],
    'completed' => [],
    'cancelled' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dhCheckToken();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $trangThaiMoi = trim((string)($_POST['trang_thai'] ?? ''));
    $ghiChu = trim((string)($_POST['ghi_chu'] ?? ''));
    if (!$id || mb_strlen($ghiChu) > 255) {
        $loi = 'Mã đơn hoặc ghi chú không hợp lệ.';
    } else {
        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare('SELECT status FROM orders WHERE id=? FOR UPDATE');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $don = $stmt->get_result()->fetch_assoc();
            $cu = $don['status'] ?? '';
            if (!$don || !in_array($trangThaiMoi, $duocPhep[$cu] ?? [], true)) {
                throw new RuntimeException('Chuyển trạng thái không hợp lệ hoặc đơn không tồn tại.');
            }

            // Hủy đơn => hoàn lại tồn kho
            if ($trangThaiMoi === 'cancelled') {
                $ct = $conn->prepare('SELECT product_id,quantity FROM order_items WHERE order_id=? FOR UPDATE');
                $ct->bind_param('i', $id);
                $ct->execute();
                $danhSachCT = $ct->get_result()->fetch_all(MYSQLI_ASSOC);
                // SỬA: không đổi products.status (web bạn chỉ có active/inactive)
                $traKho = $conn->prepare('UPDATE products SET stock_qty=stock_qty+? WHERE id=?');
                foreach ($danhSachCT as $chiTiet) {
                    if ($chiTiet['product_id'] === null) continue;
                    $maSP = (int)$chiTiet['product_id'];
                    $soLuong = (int)$chiTiet['quantity'];
                    $traKho->bind_param('ii', $soLuong, $maSP);
                    $traKho->execute();
                }
            }
            $stmt = $conn->prepare('UPDATE orders SET status=? WHERE id=?');
            $stmt->bind_param('si', $trangThaiMoi, $id);
            $stmt->execute();
            $stmt = $conn->prepare('INSERT INTO order_status_history (order_id,status,note) VALUES (?,?,?)');
            $stmt->bind_param('iss', $id, $trangThaiMoi, $ghiChu);
            $stmt->execute();
            $conn->commit();
            header('Location: capnhat_trangthai.php?updated=' . $id);
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $loi = $e instanceof RuntimeException ? $e->getMessage() : 'Lỗi cơ sở dữ liệu. Vui lòng thử lại.';
            error_log('Cap nhat don hang: ' . $e->getMessage());
        }
    }
}
if (isset($_GET['updated'])) $thongBao = 'Đã cập nhật đơn ' . maDon((int)$_GET['updated']);
$donHang = $conn->query('SELECT id,recipient_name,created_at,total_amount,status FROM orders ORDER BY id DESC LIMIT 200')->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin quản lý đơn hàng</title>
<link rel="stylesheet" href="../donhang/style-donhang.css"></head><body><main class="wrap">
<nav class="nav"><a href="admin.php">Quản trị</a><a href="quanly_tuongthich.php">Tương thích</a><a href="../index.php">Trang chủ</a><a href="../donhang/donhang.php">Đơn hàng của tôi</a></nav>
<h1>Quản trị trạng thái đơn hàng</h1><p class="muted">Mỗi lần thay đổi được lưu thành một mốc nhật ký.</p>
<?php if ($thongBao): ?><p class="notice"><?= h($thongBao) ?></p><?php endif; ?>
<?php if ($loi): ?><p class="notice bad"><?= h($loi) ?></p><?php endif; ?>
<div class="panel table-scroll"><table><thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Ngày</th><th>Tổng tiền</th><th>Hiện tại</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach ($donHang as $d): ?>
<tr><td><?= h(maDon((int)$d['id'])) ?></td><td><?= h($d['recipient_name']) ?></td>
<td><?= h(date('d/m/Y', strtotime($d['created_at']))) ?></td><td><?= h(tien($d['total_amount'])) ?></td>
<td><span class="badge" style="background:<?= h(mauTrangThai($d['status'])) ?>"><?= h(tenTrangThai($d['status'])) ?></span></td>
<td><?php $tuyChon = $duocPhep[$d['status']] ?? []; ?>
<?php if ($tuyChon): ?><form method="post">
<input type="hidden" name="csrf_token" value="<?= h(dhToken()) ?>">
<input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
<select name="trang_thai" aria-label="Chọn trạng thái mới">
<?php foreach ($tuyChon as $ma): ?><option value="<?= h($ma) ?>"><?= h(tenTrangThai($ma)) ?></option><?php endforeach; ?>
</select><input type="text" name="ghi_chu" maxlength="255" placeholder="Ghi chú (nếu có)">
<button class="btn" type="submit" style="margin-top:8px">Cập nhật</button>
</form><?php else: ?><span class="muted">Đã kết thúc</span><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></main></body></html>
