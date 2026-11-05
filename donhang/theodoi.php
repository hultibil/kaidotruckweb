<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/trangthai.php';
$userId = dhRequireLogin();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Mã đơn không hợp lệ.'); }

// Chỉ cho xem đơn thuộc tài khoản đang đăng nhập
$stmt = $conn->prepare('SELECT * FROM orders WHERE id=? AND user_id=? LIMIT 1');
$stmt->bind_param('ii', $id, $userId);
$stmt->execute();
$don = $stmt->get_result()->fetch_assoc();
if (!$don) { http_response_code(404); exit('Không tìm thấy đơn hàng thuộc tài khoản của bạn.'); }

$stmt = $conn->prepare('SELECT product_name, unit_price, quantity FROM order_items WHERE order_id=? ORDER BY id');
$stmt->bind_param('i', $id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare('SELECT status, note, created_at FROM order_status_history WHERE order_id=? ORDER BY id ASC');
$stmt->bind_param('i', $id);
$stmt->execute();
$lichSu = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$buoc = array_keys($DS_TRANG_THAI);
$viTriNow = array_search($don['status'], $buoc, true);
$daHuy = ($don['status'] === 'cancelled');
$thanhToan = ($don['payment_method'] === 'cod' || $don['payment_method'] === null || $don['payment_method'] === '')
    ? 'Thanh toán khi nhận hàng (COD)'
    : (string)$don['payment_method'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theo dõi đơn <?= h(maDon((int)$id)) ?> - Kaido</title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="../css/cart.css">
    <style>
        /* Bổ sung cho trang theo dõi đơn (màu trung tính để hợp cả nền sáng lẫn tối) */
        .td-badge { display: inline-block; padding: 3px 12px; border-radius: 14px; color: #fff; font-weight: 600; font-size: .9em; }
        .td-notice { background: #fdecec; color: #a63d3d; padding: 10px 14px; border-radius: 8px; }
        .td-timeline { display: flex; flex-wrap: wrap; gap: 8px; margin: 18px 0; }
        .td-step { flex: 1; min-width: 120px; text-align: center; opacity: .55; }
        .td-step.done { opacity: 1; }
        .td-step.current { font-weight: 700; }
        .td-dot { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; border: 2px solid currentColor; font-weight: 700; margin-bottom: 6px; }
        .td-step.done .td-dot { background: #17834a; border-color: #17834a; color: #fff; }
        .td-step.current .td-dot { box-shadow: 0 0 0 4px rgba(23, 131, 74, .3); }
        .td-scroll { overflow-x: auto; }
        .td-table { width: 100%; border-collapse: collapse; }
        .td-table th, .td-table td { padding: 9px 10px; border-bottom: 1px solid rgba(128, 128, 128, .35); text-align: left; }
        .td-table .right { text-align: right; }
        .td-history { list-style: none; padding: 0; margin: 0; }
        .td-history li { padding: 8px 0 8px 14px; margin-left: 6px; border-left: 3px solid rgba(128, 128, 128, .45); }
        .td-muted { opacity: .7; font-size: .9em; margin-left: 8px; }
    </style>
</head>

<body class="cart-page">
    <div class="header"> <!--Add CSS-->
            <img class="logo" src="../image/kaidologo.jpg">
            <h2>Theo dõi đơn hàng</h2>
            <div class="nut0">
                <a href="../index.php" onclick="clickSound()">Trang chủ</a>
            </div>
            <div class="nut1"> <!--Add CSS-->
                <a href="../khac/khac.html" onclick="clickSound()">Dự án khác</a>
            </div>
            <div class="nut2">
                <a href="../hoithem.html" onclick="clickSound()">Trò chuyện</a>
            </div>
            <div class="nut4chon">
                <a href="../tienich.php" onclick="clickSound()">Tài khoản/Mua hàng</a>
            </div>
            <div class="nut5">
                <a href="../kythuat/details.html">Chi tiết kỹ thuật</a>
            </div>
            <div class="nut6">
                <a href="../Blog/blog.html" onclick="clickSound()">Tin tức</a>
            </div>
            <div class="nut3">
                <a href="../gamemini.html" onclick="clickSound()">Game mini</a>
            </div>
    </div>
    <div class="cart-container">

        <div class="cart-header">
            <h1>Đơn hàng <?= h(maDon((int)$id)) ?></h1>
            <p>Theo dõi tiến trình đơn hàng của bạn</p>
        </div>

        <div class="cart-card">

            <p>Trạng thái hiện tại:
                <span class="td-badge" style="background:<?= h(mauTrangThai($don['status'])) ?>"><?= h(tenTrangThai($don['status'])) ?></span>
            </p>

            <?php if ($daHuy): ?>
                <p class="td-notice">Đơn hàng này đã bị hủy.</p>
            <?php else: ?>
                <div class="td-timeline">
                    <?php foreach ($buoc as $i => $ma):
                        $done = $viTriNow !== false && $i <= $viTriNow;
                        $cls = $done ? 'done' : '';
                        if ($ma === $don['status']) $cls .= ' current';
                    ?>
                    <div class="td-step <?= h(trim($cls)) ?>">
                        <span class="td-dot"><?= $done ? '✓' : ($i + 1) ?></span>
                        <div><?= h($DS_TRANG_THAI[$ma]) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h2>Thông tin nhận hàng</h2>
            <p>
                <b>Người nhận:</b> <?= h($don['recipient_name']) ?><br>
                <b>SĐT:</b> <?= h($don['phone']) ?><br>
                <b>Địa chỉ:</b> <?= h($don['shipping_address']) ?><br>
                <b>Ngày đặt:</b> <?= h(date('d/m/Y H:i', strtotime($don['created_at']))) ?><br>
                <b>Thanh toán:</b> <?= h($thanhToan) ?>
            </p>

            <h2>Sản phẩm đã mua</h2>
            <div class="td-scroll">
                <table class="td-table">
                    <thead>
                        <tr><th>Sản phẩm</th><th>Đơn giá</th><th>Số lượng</th><th>Thành tiền</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= h($it['product_name']) ?></td>
                            <td><?= h(tien($it['unit_price'])) ?></td>
                            <td><?= (int)$it['quantity'] ?></td>
                            <td><?= h(tien($it['unit_price'] * $it['quantity'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="3" class="right"><b>Tổng tiền:</b></td>
                            <td><b><?= h(tien($don['total_amount'])) ?></b></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <div class="cart-card">
            <h2>Nhật ký trạng thái</h2>
            <?php if (!$lichSu): ?>
                <p class="td-muted">Đơn hàng này chưa có nhật ký trạng thái.</p>
            <?php endif; ?>
            <ol class="td-history">
                <?php foreach ($lichSu as $moc): ?>
                <li>
                    <strong><?= h(tenTrangThai($moc['status'])) ?></strong>
                    <span class="td-muted"><?= h(date('d/m/Y H:i:s', strtotime($moc['created_at']))) ?></span>
                    <?php if ($moc['note']): ?><div><?= h($moc['note']) ?></div><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>

        <div class="cart-footer">
            <a href="donhang.php">← Lịch sử mua hàng</a>
            <a href="../sanpham/shop.php">KAIDO SHOP</a>
            <a href="../Giohang/giohang.php">Giỏ hàng →</a>
        </div>

    </div>
</body>
</html>
