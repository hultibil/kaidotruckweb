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
        /* --- TÙY CHỈNH UI TRANG THEO DÕI ĐƠN HÀNG --- */
        .td-badge { 
            display: inline-block; 
            padding: 5px 14px; 
            border-radius: 20px; 
            color: #fff; 
            font-weight: 600; 
            font-size: .9em; 
        }
        .td-notice { 
            background: rgba(239, 68, 68, 0.15); 
            border: 1px solid #ef4444; 
            color: #fca5a5; 
            padding: 14px 18px; 
            border-radius: 10px; 
            font-weight: 500;
            margin: 15px 0;
        }
        
        /* Timeline hiện đại */
        .td-timeline { 
            display: flex; 
            justify-content: space-between;
            position: relative; 
            margin: 35px 15px 25px 15px; 
        }
        .td-timeline::before {
            content: '';
            position: absolute;
            top: 18px;
            left: 40px;
            right: 40px;
            height: 3px;
            background: #374151;
            z-index: 1;
        }
        .td-step { 
            position: relative;
            z-index: 2;
            flex: 1; 
            text-align: center; 
            opacity: 0.4; 
            transition: opacity 0.3s; 
        }
        .td-step.done, .td-step.current { 
            opacity: 1; 
        }
        .td-dot { 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            width: 38px; 
            height: 38px; 
            border-radius: 50%; 
            background: #111827; 
            border: 2px solid #4b5563; 
            color: #9ca3af; 
            font-weight: bold; 
            margin-bottom: 8px; 
            transition: all 0.3s;
        }
        .td-step.done .td-dot { 
            background: #10b981; 
            border-color: #10b981; 
            color: #fff; 
            box-shadow: 0 0 12px rgba(16, 185, 129, 0.4);
        }
        .td-step.current .td-dot { 
            background: #3b82f6; 
            border-color: #3b82f6; 
            color: #fff; 
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.6);
            transform: scale(1.1);
        }
        .td-step div {
            font-size: 13px;
            font-weight: 500;
            color: #d1d5db;
        }

        /* Lưới thông tin nhận hàng */
        .td-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 15px;
            margin: 15px 0 25px 0;
        }
        .td-info-box {
            background: rgba(17, 24, 39, 0.5);
            border: 1px solid #374151;
            border-radius: 10px;
            padding: 16px;
            font-size: 14px;
            line-height: 1.6;
        }
        .td-info-box strong {
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
            color: #38bdf8;
        }

        /* Bảng sản phẩm */
        .td-scroll { overflow-x: auto; margin-top: 10px; }
        .td-table { width: 100%; border-collapse: collapse; }
        .td-table th, .td-table td { padding: 12px 14px; border-bottom: 1px solid #374151; text-align: left; font-size: 14px; }
        .td-table th { background: #111827; color: #9ca3af; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        .td-table .right { text-align: right; }
        .td-table tr:hover { background: rgba(255, 255, 255, 0.02); }

        /* Nhật ký trạng thái */
        .td-history { list-style: none; padding: 0; margin: 0; }
        .td-history li { 
            position: relative;
            padding: 10px 0 10px 22px; 
            margin-left: 10px; 
            border-left: 2px solid #4b5563; 
            font-size: 14px; 
        }
        .td-history li::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 14px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #3b82f6;
        }
        .td-muted { color: #9ca3af; font-size: 0.85em; margin-left: 8px; }

        /* --- LIGHT MODE STYLING --- */
        body.light-mode.cart-page, body.light-mode {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
        }
        body.light-mode .cart-card {
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #0f172a !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05) !important;
        }
        body.light-mode .cart-header h1,
        body.light-mode .cart-card h2 {
            color: #0f172a !important;
        }
        body.light-mode .cart-card p {
            color: #334155 !important;
        }
        body.light-mode .td-info-box {
            background: #f8fafc !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
        }
        body.light-mode .td-info-box strong {
            color: #0284c7 !important;
        }
        body.light-mode .td-table th {
            background: #f1f5f9 !important;
            color: #475569 !important;
        }
        body.light-mode .td-table td {
            border-bottom: 1px solid #e2e8f0 !important;
            color: #1e293b !important;
        }
        body.light-mode .td-timeline::before {
            background: #cbd5e1 !important;
        }
        body.light-mode .td-dot {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #64748b;
        }
        body.light-mode .td-step div {
            color: #475569 !important;
        }
        body.light-mode .td-history li {
            border-left-color: #cbd5e1 !important;
        }
        body.light-mode .td-muted {
            color: #64748b !important;
        }
        body.light-mode .theme-toggle-btn {
            background: rgba(0,0,0,0.08) !important;
            border: 1px solid rgba(0,0,0,0.2) !important;
            color: #0f172a !important;
        }
    </style>
</head>

<body class="cart-page">
    <!-- THANH MÀU XÁM (Đầu trang - Smart Header) -->
    <div class="header" id="mainHeader">
        <div class="header-left">
            <img class="logo" src="../image/kaidologo.jpg">
            <h2>Theo dõi đơn hàng</h2>
        </div>
        <div class="header-right">
            <!-- NÚT BẤM CHUYỂN ĐỔI DARK / LIGHT MODE -->
            <button id="themeToggle" class="theme-toggle-btn" onclick="toggleTheme()">
                🌙 Dark
            </button>

            <!-- HIỂN THỊ TÊN TÀI KHOẢN HOẶC ĐĂNG NHẬP -->
            <?php if (isset($_SESSION['username'])): ?>
                <div class="user-header-badge">
                    <span>👋 Hi, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
                </div>
            <?php else: ?>
                <div class="user-header-badge visitor">
                    <a href="../Taikhoan/dangnhap.php">🔑 Đăng nhập/ đăng ký</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- THANH ĐIỀU HƯỚNG NGANG -->
    <div id="dautrang"> 
    <div class="nav-container">
        <div class="nav-scroll-wrapper">
            <div class="nut0"><a href="../index.php">Trang chủ</a></div>
            <div class="nut1"><a href="../khac/khac.php">Dự án khác</a></div>
            <div class="nut2"><a href="../hoithem.php">Trò chuyện</a></div>
            <div class="nut4chon"><a href="../tienich.php">Tài khoản/Mua hàng</a></div>
            <div class="nut5"><a href="../kythuat/details.php">Chi tiết kỹ thuật</a></div>
            <div class="nut6"><a href="../Blog/blog.php">Tin tức</a></div>
            <div class="nut3"><a href="../gamemini.php">Game mini</a></div>
        </div>
    </div>

    <div class="cart-container">
        <div class="cart-header">
            <h1>Đơn hàng <?= h(maDon((int)$id)) ?></h1>
            <p>Theo dõi tiến trình và thông tin chi tiết đơn hàng của bạn</p>
        </div>

        <div class="cart-card" style="padding: 25px; margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
                <h2 style="margin: 0; font-size: 20px;">Trạng thái đơn hàng</h2>
                <span class="td-badge" style="background:<?= h(mauTrangThai($don['status'])) ?>"><?= h(tenTrangThai($don['status'])) ?></span>
            </div>

            <?php if ($daHuy): ?>
                <div class="td-notice">⚠️ Đơn hàng này đã bị hủy.</div>
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
        </div>

        <div class="cart-card" style="padding: 25px; margin-bottom: 25px;">
            <h2 style="font-size: 20px; margin-bottom: 15px;">Thông tin chi tiết</h2>
            
            <div class="td-info-grid">
                <div class="td-info-box">
                    <strong>📍 Địa chỉ nhận hàng</strong>
                    <b>Người nhận:</b> <?= h($don['recipient_name']) ?><br>
                    <b>Số điện thoại:</b> <?= h($don['phone']) ?><br>
                    <b>Địa chỉ:</b> <?= h($don['shipping_address']) ?>
                </div>
                <div class="td-info-box">
                    <strong>💳 Thanh toán & Thời gian</strong>
                    <b>Ngày đặt:</b> <?= h(date('d/m/Y H:i', strtotime($don['created_at']))) ?><br>
                    <b>Hình thức:</b> <?= h($thanhToan) ?>
                </div>
            </div>

            <h2 style="font-size: 18px; margin: 25px 0 10px 0;">Sản phẩm trong đơn</h2>
            <div class="td-scroll">
                <table class="td-table">
                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th>Đơn giá</th>
                            <th>Số lượng</th>
                            <th class="right">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                        <tr>
                            <td><strong><?= h($it['product_name']) ?></strong></td>
                            <td><?= h(tien($it['unit_price'])) ?></td>
                            <td><?= (int)$it['quantity'] ?></td>
                            <td class="right" style="color: #34d399; font-weight: bold;"><?= h(tien($it['unit_price'] * $it['quantity'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="3" class="right" style="font-size: 16px;"><b>Tổng thanh toán:</b></td>
                            <td class="right" style="font-size: 18px; color: #ff416c; font-weight: bold;"><?= h(tien($don['total_amount'])) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="cart-card" style="padding: 25px; margin-bottom: 25px;">
            <h2 style="font-size: 20px; margin-bottom: 15px;">Nhật ký trạng thái</h2>
            <?php if (!$lichSu): ?>
                <p class="td-muted">Đơn hàng này chưa có nhật ký trạng thái.</p>
            <?php else: ?>
                <ul class="td-history">
                    <?php foreach ($lichSu as $moc): ?>
                    <li>
                        <strong><?= h(tenTrangThai($moc['status'])) ?></strong>
                        <span class="td-muted"><?= h(date('d/m/Y H:i:s', strtotime($moc['created_at']))) ?></span>
                        <?php if ($moc['note']): ?><div style="margin-top: 4px; color: #9ca3af;"><?= h($moc['note']) ?></div><?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="cart-footer">
            <a href="donhang.php">← Lịch sử mua hàng</a>
            <a href="../sanpham/shop.php">🛍️ Tiếp tục mua sắm</a>
        </div>
    </div>

    <!-- Script xử lý Dark/Light Mode -->
    <script>
    document.addEventListener('DOMContentLoaded', initTheme);
    </script>
</body>
<script src="../js/slide.js"></script>
</html>