<?php
/**
 * danhgia_cho.php - "Đánh giá của tôi": sản phẩm đã giao thành công đang chờ đánh giá
 * (có thể bỏ qua) và các đánh giá đã gửi.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/danhgia_helper.php';

$uid = rv_require_login();

// Sản phẩm trong các đơn đã giao mà người này chưa đánh giá (mỗi sản phẩm hiện 1 lần, lấy đơn mới nhất)
$stmt = $conn->prepare(
    "SELECT MAX(o.id) AS order_id, oi.product_id, MAX(oi.product_name) AS product_name,
            MAX(p.image_path) AS image_path, MAX(o.updated_at) AS delivered_at
       FROM orders o
       JOIN order_items oi ON oi.order_id = o.id
       LEFT JOIN products p ON p.id = oi.product_id
       LEFT JOIN product_reviews r ON r.user_id = o.user_id AND r.product_id = oi.product_id
      WHERE o.user_id = ? AND o.status = 'completed' AND r.id IS NULL
      GROUP BY oi.product_id
      ORDER BY delivered_at DESC"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$pending = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $pending[] = $row;
}
$stmt->close();

// Các đánh giá đã gửi
$stmt = $conn->prepare(
    "SELECT r.product_id, r.order_id, r.rating, r.comment, r.status, r.created_at,
            p.product_name, p.image_path
       FROM product_reviews r
       JOIN products p ON p.id = r.product_id
      WHERE r.user_id = ?
      ORDER BY r.updated_at DESC"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$mine = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $mine[] = $row;
}
$stmt->close();

$rv_title = 'Đánh giá của tôi';
$rv_sub   = 'Sản phẩm đã giao thành công đang chờ bạn đánh giá';
require __DIR__ . '/danhgia_dau.php';
?>
        <div class="account-card rv-card">
            <h2 style="margin-top:0">Chờ đánh giá (<?= count($pending) ?>)</h2>
            <?php if (!$pending): ?>
                <p class="rv-hint">Bạn không còn sản phẩm nào cần đánh giá. 🎉</p>
            <?php endif; ?>
            <?php foreach ($pending as $p): ?>
                <div class="rv-row">
                    <div class="info">
                        <?php if (!empty($p['image_path'])): ?><img src="<?= rv_h(rv_product_img($p['image_path'])) ?>" alt=""><?php endif; ?>
                        <div><strong><?= rv_h($p['product_name']) ?></strong>
                            <div class="rv-hint">Giao thành công: <?= rv_h(date('d/m/Y', strtotime($p['delivered_at']))) ?> · Đơn #<?= (int)$p['order_id'] ?></div></div>
                    </div>
                    <a class="rv-btn" href="danhgia.php?order_id=<?= (int)$p['order_id'] ?>&amp;product_id=<?= (int)$p['product_id'] ?>">⭐ Đánh giá</a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="account-card rv-card">
            <h2 style="margin-top:0">Đánh giá đã gửi (<?= count($mine) ?>)</h2>
            <?php if (!$mine): ?><p class="rv-hint">Bạn chưa gửi đánh giá nào.</p><?php endif; ?>
            <?php foreach ($mine as $m): ?>
                <div class="rv-row">
                    <div class="info">
                        <?php if (!empty($m['image_path'])): ?><img src="<?= rv_h(rv_product_img($m['image_path'])) ?>" alt=""><?php endif; ?>
                        <div><strong><?= rv_h($m['product_name']) ?></strong>
                            <div><span class="rv-star-on"><?= rv_h(rv_stars((int)$m['rating'])) ?></span>
                                <?php if ($m['status'] === 'hidden'): ?><span class="rv-hint">(đang bị ẩn bởi cửa hàng)</span><?php endif; ?></div>
                            <?php if (!empty($m['comment'])): ?><div class="rv-hint"><?= rv_h(mb_strimwidth($m['comment'], 0, 90, '…', 'UTF-8')) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <a class="rv-btn ghost" href="danhgia.php?order_id=<?= (int)$m['order_id'] ?>&amp;product_id=<?= (int)$m['product_id'] ?>">Sửa</a>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="account-footer">
            <a href="../Taikhoan/taikhoan.php">← Về trang tài khoản</a>
            <a href="../donhang/donhang.php">Đơn hàng của tôi</a>
        </div>
<?php require __DIR__ . '/danhgia_cuoi.php'; ?>
