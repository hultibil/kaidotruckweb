<?php
// Đặt tại: donhang/lichsu.php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/trangthai.php';
$userId = dhRequireLogin();

$stmt = $conn->prepare(
    'SELECT o.id, o.created_at, o.total_amount, o.status,
            (SELECT COALESCE(SUM(i.quantity),0) FROM order_items i WHERE i.order_id=o.id) AS tong_so_luong
     FROM orders o WHERE o.user_id=? ORDER BY o.created_at DESC, o.id DESC'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$donHang = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lịch sử mua hàng - Kaido Shop</title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* CSS tuỳ chỉnh đồng bộ giao diện cho trang Lịch sử đơn hàng */
        .history-container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 15px;
        }
        .history-panel {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            margin-top: 20px;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .history-table th, .history-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 14px;
        }
        .history-table th {
            color: #94a3b8;
            font-weight: 600;
            background: rgba(0, 0, 0, 0.2);
        }
        .history-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }
        .badge-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            color: #fff;
            font-size: 12px;
            font-weight: bold;
        }
        .btn-track {
            background: linear-gradient(to right, #ff416c, #ff4b2b);
            color: white;
            padding: 6px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            font-size: 13px;
            display: inline-block;
            transition: opacity 0.2s;
        }
        .btn-track:hover {
            opacity: 0.85;
        }
        .empty-history {
            text-align: center;
            background: rgba(0, 0, 0, 0.2);
            padding: 40px;
            border-radius: 15px;
            margin-top: 30px;
        }
        .empty-history a {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background: linear-gradient(to right, #ff416c, #ff4b2b);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }
        .muted-text {
            color: #94a3b8;
            font-size: 14px;
        }
    </style>
</head>
<body>

<!-- HEADER MENU CHUNG -->
<div class="header">
    <img class="logo" src="../image/kaidologo.jpg" alt="Logo">
    <h2>TRUNG TÂM CỬA HÀNG LINH KIỆN & THIẾT BỊ ĐIỆN TỬ</h2>
    <div class="nut0"><a href="../index.php">Trang chủ</a></div>
    <div class="nut1"><a href="../khac/khac.html">Dự án khác</a></div>
    <div class="nut2"><a href="../hoithem.html">Trò chuyện</a></div>
    <div class="nut4chon"><a href="../tienich.php">Tài khoản/Mua hàng</a></div>
    <div class="nut5"><a href="../kythuat/details.html">Chi tiết kỹ thuật</a></div>
    <div class="nut6"><a href="../Blog/blog.html">Tin tức</a></div>
    <div class="nut3"><a href="../gamemini.html">Game mini</a></div>
</div>

<div class="history-container">
    <h1>🧾 LỊCH SỬ MUA HÀNG</h1>
    <p class="muted-text" style="text-align: center; margin-bottom: 25px;">Danh sách các đơn hàng đã được gắn với tài khoản đang đăng nhập của bạn.</p>

    <?php if (!$donHang): ?>
        <div class="empty-history">
            <p style="font-size: 18px; color: #aaa;">Bạn chưa có đơn hàng nào.</p>
            <a href="../sanpham/shop.php">Mua sắm ngay</a>
        </div>
    <?php else: ?>
        <div class="history-panel">
            <div style="overflow-x: auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Ngày đặt</th>
                            <th>Số sản phẩm</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($donHang as $d): ?>
                        <tr>
                            <td><strong style="color: #38bdf8;"><?= h(maDon((int)$d['id'])) ?></strong></td>
                            <td><?= h(date('d/m/Y H:i', strtotime($d['created_at']))) ?></td>
                            <td><?= (int)$d['tong_so_luong'] ?> sản phẩm</td>
                            <td><strong style="color: #34d399;"><?= h(tien($d['total_amount'])) ?></strong></td>
                            <td>
                                <span class="badge-status" style="background: <?= h(mauTrangThai($d['status'])) ?>;">
                                    <?= h(tenTrangThai($d['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <a class="btn-track" href="theodoi.php?id=<?= (int)$d['id'] ?>">Theo dõi đơn</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="quaylai">
    <a href="../tienich.php">← Quay lại trang tiện ích</a>
</div>

</body>
</html>