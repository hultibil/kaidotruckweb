<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/trangthai.php';
$userId = dhRequireLogin();

// Thư mục chứa ảnh sản phẩm (giống shop.php)
$THU_MUC_ANH = '../image/uploads/';

// Danh sách đơn của tài khoản đang đăng nhập, mới nhất trước
$stmt = $conn->prepare(
    'SELECT id, created_at, total_amount, status
     FROM orders WHERE user_id=? ORDER BY created_at DESC, id DESC'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$donHang = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Sản phẩm của tất cả các đơn đó, lấy một lần rồi nhóm theo đơn
$spTheoDon = [];
if ($donHang) {
    $stmt = $conn->prepare(
        'SELECT oi.order_id, oi.product_name, oi.quantity, oi.unit_price, p.image_path
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE o.user_id=?
         ORDER BY oi.order_id, oi.id'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $it) {
        $spTheoDon[(int)$it['order_id']][] = $it;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng - Kaido</title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="../css/cart.css">
</head>

<body class="cart-page">
    <div class="header"> <!--Add CSS-->
            <img class="logo" src="../image/kaidologo.jpg">
            <h2>Đơn hàng của bạn</h2>
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
            <h1>Đơn hàng của tôi</h1>
            <p>Theo dõi các đơn hàng đã đặt</p>
        </div>

        <?php if (!$donHang): ?>

            <div class="cart-card">
                <p>Bạn chưa có đơn hàng nào.</p>
                <p><a href="../sanpham/shop.php">Mua sắm tại KAIDO SHOP</a></p>
            </div>

        <?php else: ?>

            <?php foreach ($donHang as $d):
                $dsSP = $spTheoDon[(int)$d['id']] ?? [];
                $dau = $dsSP[0] ?? null;
                $conLai = max(0, count($dsSP) - 1);
                $tongSL = 0;
                foreach ($dsSP as $x) $tongSL += (int)$x['quantity'];

                $tenAnh = $dau ? trim((string)($dau['image_path'] ?? '')) : '';
                $anh = ($tenAnh !== '' && file_exists(__DIR__ . '/' . $THU_MUC_ANH . $tenAnh))
                    ? $THU_MUC_ANH . $tenAnh
                    : '../image/kaidoLogomini.png';
            ?>
            <!-- Đơn hàng <?= h(maDon((int)$d['id'])) ?> -->
            <div class="order-card">

                <div class="order-header">
                    <div>
                        <strong>Đơn hàng #<?= h(maDon((int)$d['id'])) ?></strong>
                        <p>Ngày đặt: <?= h(date('d/m/Y H:i', strtotime($d['created_at']))) ?></p>
                    </div>

                    <span class="order-status" style="background:<?= h(mauTrangThai($d['status'])) ?>;color:#fff">
                        <?= h(tenTrangThai($d['status'])) ?>
                    </span>
                </div>

                <?php if ($dau): ?>
                <div class="order-product">
                    <div class="product-image">
                        <img src="<?= h($anh) ?>" alt="<?= h($dau['product_name']) ?>">
                    </div>

                    <div class="product-info">
                        <h2><?= h($dau['product_name']) ?></h2>
                        <p>Số lượng: <?= (int)$dau['quantity'] ?></p>
                        <p><?= h(tien($dau['unit_price'] * $dau['quantity'])) ?></p>
                        <?php if ($conLai > 0): ?>
                            <p>và <?= $conLai ?> sản phẩm khác (tổng <?= $tongSL ?> sản phẩm)</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="order-bottom">
                    <span>Tổng tiền</span>
                    <strong><?= h(tien($d['total_amount'])) ?></strong>
                </div>

                <form action="theodoi.php" method="get">
                    <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                    <button class="detail-btn" type="submit">
                        Xem chi tiết
                    </button>
                </form>

            </div>
            <?php endforeach; ?>

        <?php endif; ?>

        <div class="cart-footer">
            <a href="../tienich.php">← Trở về</a>
            <a href="../sanpham/shop.php">KAIDO SHOP</a>
            <a href="../Giohang/giohang.php">Giỏ hàng →</a>
        </div>
    </div>
</body>
</html>
