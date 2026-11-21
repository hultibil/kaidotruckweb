<?php
require_once __DIR__ . '/baove.php';
require_once __DIR__ . '/../donhang/trangthai.php';

$now = new DateTimeImmutable();
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]);
$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
$year = $year ?: (int)$now->format('Y');
$month = $month ?: (int)$now->format('n');
$start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
$end = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d H:i:s');

// Số đơn lập trong tháng, kể cả đơn chuyển trạng thái sau đó.
$stmt = $conn->prepare("SELECT COUNT(*) AS total,
    SUM(status = 'pending') AS pending,
    SUM(status = 'confirmed') AS confirmed,
    SUM(status = 'shipping') AS shipping,
    SUM(status = 'cancelled') AS cancelled
    FROM orders WHERE created_at >= ? AND created_at < ?");
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$created = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Đơn hoàn thành được ghi nhận vào tháng hoàn thành, không tính đơn hủy.
$stmt = $conn->prepare("SELECT COUNT(*) AS completed, COALESCE(SUM(total_amount), 0) AS revenue
    FROM orders WHERE status = 'completed' AND updated_at >= ? AND updated_at < ?");
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$sales = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT oi.product_id, oi.product_name,
    SUM(oi.quantity) AS quantity, SUM(oi.quantity * oi.unit_price) AS revenue
    FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id
    WHERE o.status = 'completed' AND o.updated_at >= ? AND o.updated_at < ?
    GROUP BY oi.product_id, oi.product_name
    ORDER BY quantity DESC, revenue DESC, oi.product_id ASC");
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$totalItems = array_sum(array_column($products, 'quantity'));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống kê &amp; báo cáo - Kaido Shop</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="image/kaidoLogomini.png">
    <style>
        /* === BỐ CỤC CHUNG KIỂU ADMINLTE DÙNG MÀU RIÊNG KAIDO (ĐỒNG BỘ ADMIN.PHP) === */
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            background: #0f172a;
            color: #f1f5f9;
            font-family: 'HarmonyOS Sans', system-ui, sans-serif;
            display: flex;
            min-height: 100vh;
        }

        /* 1. SIDEBAR TRÁI */
        .admin-sidebar {
            width: 235px;
            flex-shrink: 0;
            background: #1e293b;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            z-index: 10;
        }

        .admin-sidebar {
        width: 235px;          /* Chiều rộng mặc định khi mới tải trang */
        min-width: 235px;      /* Giới hạn nhỏ nhất khi thu lại */
        max-width: 450px;      /* Giới hạn lớn nhất khi kéo rộng ra */
        resize: horizontal;    /* Kích hoạt tính năng kéo dãn theo chiều ngang */
        overflow: auto;        /* Bắt buộc phải có overflow để trình duyệt hiển thị tính năng kéo */
        flex-shrink: 0;
}   


        .brand-logo-container {
            padding: 20px 15px;
            text-align: center;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .brand-logo-container img {
            max-width: 140px;
            height: auto;
            object-fit: contain;
            border-radius: 8px;
            transition: transform 0.3s;
        }
        .brand-logo-container img:hover {
            transform: scale(1.05);
        }
        .brand-name {
            font-size: 14px;
            font-weight: bold;
            color: #94a3b8;
            margin-top: 8px;
            letter-spacing: 1px;
        }

        .admin-profile {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff416c, #ff4b2b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            color: white;
            box-shadow: 0 2px 8px rgba(255, 65, 108, 0.4);
        }
        .admin-info-text {
            font-size: 14px;
            line-height: 1.3;
        }
        .admin-info-text span {
            display: block;
            color: #64748b;
            font-size: 12px;
        }

        .sidebar-menu {
            padding: 15px 10px;
            flex: 1;
        }
        .menu-heading {
            font-size: 11px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
            padding: 10px 15px 5px;
            letter-spacing: 0.5px;
        }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 6px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.25s ease;
        }
        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
            transform: translateX(4px);
        }
        .sidebar-menu a.active {
            background: linear-gradient(90deg, #ff416c, #ff4b2b);
            color: white;
            box-shadow: 0 4px 12px rgba(255, 65, 108, 0.3);
        }

        /* 2. KHU VỰC HIỂN THỊ NỘI DUNG (MAIN DASHBOARD BÊN PHẢI) */
        .admin-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            background: #0f172a;
        }

        .main-header {
            height: 65px;
            background: #1e293b;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }
        .header-title {
            font-size: 18px;
            font-weight: bold;
            color: #f8fafc;
        }
        .topbar-actions a {
            color: #94a3b8;
            text-decoration: none;
            margin-left: 18px;
            font-size: 14px;
            transition: color 0.2s;
        }
        .topbar-actions a:hover {
            color: #ff416c;
        }

        .content-body {
            padding: 30px;
            flex: 1;
        }

        .card-box {
            background: #1e293b;
            border-radius: 12px;
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
            margin-bottom: 22px;
        }

        /* === STYLES RIÊNG CHO TRANG THỐNG KÊ (TƯƠNG THÍCH GLASSY) === */
        .muted {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.5;
        }
        .toolbar {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin: 20px 0 4px;
        }
        .toolbar label {
            display: grid;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
        }
        .toolbar select,
        .toolbar input {
            padding: 9px 12px;
            border-radius: 8px;
            border: 1px solid #334155;
            background: #0f172a;
            color: white;
            font: inherit;
            font-size: 14px;
        }
        .btn-action {
            padding: 9px 16px;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: opacity 0.2s, transform 0.15s;
        }
        .btn-save { background: #10b981; }
        .btn-print {
            background: var(--paper, rgba(255, 255, 255, 0.1));
            color: var(--ink, #f8fafc) !important;
            border: 1px solid var(--border, #475569);
        }
        .btn-action:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Grid các thẻ KPI thống kê */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }
        .stat-card {
            background: #1e293b;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: 0 8px 20px -5px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .stat-card span {
            font-size: 13px;
            color: var(--muted, #94a3b8);
            font-weight: 600;
        }
        .stat-card strong {
            display: block;
            font-size: 24px;
            margin-top: 10px;
            color: var(--accent, #38bdf8);
            letter-spacing: -0.02em;
        }
        .stat-card.revenue strong {
            color: #10b981;
        }
        .stat-card.cancelled strong {
            color: #ef4444;
        }

        /* Bảng dữ liệu chuẩn admin.php */
        table.admin-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 15px;
            background: transparent;
        }
        table.admin-table th, table.admin-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 14px;
        }
        table.admin-table th {
            background: rgba(15, 23, 42, 0.6);
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        table.admin-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }
        table.admin-table th.number,
        table.admin-table td.number {
            text-align: right;
            white-space: nowrap;
        }
        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 12px;
            background: rgba(56, 189, 248, 0.18);
            color: var(--accent, #38bdf8);
        }
        .rank-badge.top-1 { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; }
        .rank-badge.top-2 { background: linear-gradient(135deg, #94a3b8, #64748b); color: #fff; }
        .rank-badge.top-3 { background: linear-gradient(135deg, #b45309, #92400e); color: #fff; }

        /* Responsive Mobile Layout */
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .admin-sidebar { width: 100%; }
            .main-header { padding: 0 15px; }
            .content-body { padding: 15px; }
        }

        /* Chế độ In / Xuất PDF */
        @media print {
            body.glassy-page, body {
                background: white !important;
                color: black !important;
                display: block !important;
            }
            .admin-sidebar, .main-header, .toolbar, .no-print, .glassy-theme-toggle {
                display: none !important;
            }
            .admin-main {
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                background: white !important;
            }
            .content-body {
                padding: 0 !important;
            }
            .card-box, .stat-card {
                background: white !important;
                border: 1px solid #bbb !important;
                box-shadow: none !important;
                backdrop-filter: none !important;
                break-inside: avoid;
                color: black !important;
            }
            .muted, th, td, .stat-card span, .stat-card strong, h2 {
                color: black !important;
                background: transparent !important;
            }
        }
    </style>
    <link rel="stylesheet" href="../css/glassy.css">
    <script src="../js/glassy-theme.js"></script>
</head>
<body class="glassy-page glassy-native-admin">

    <!-- 1. BÊN TRÁI: SIDEBAR ĐA NHIỆM ĐỒNG BỘ VỚI ADMIN.PHP -->
    <aside class="admin-sidebar no-print">
        <div class="brand-logo-container">
            <a href="../index.php">
                <img src="../image/logo.png" alt="Logo Kaido Shop" onerror="this.onerror=null; this.src='../image/kaidologo.jpg';">
            </a>
            <div class="brand-name">KAIDO ADMIN HUB</div>
        </div>

        <div class="admin-profile">
            <div class="avatar-circle">
                <?= strtoupper(substr(h($admin_username ?? 'A'), 0, 1)) ?>
            </div>
            <div class="admin-info-text">
                <strong><?= h($admin_username ?? 'Admin') ?></strong>
                <span>Quản trị viên</span>
            </div>
        </div>

        <button type="button" class="glassy-theme-toggle">Chuyển giao diện</button>
        <nav class="sidebar-menu">
            <div class="menu-heading">Bảng Điều Khiển</div>
            <a href="admin.php?tab=products">Quản lý Sản phẩm</a>
            <a href="admin.php?tab=orders">Quản lý Đơn hàng</a>
            <a href="admin.php?tab=users">Quản lý Người dùng</a>
            <a href="quanly_tuongthich.php">Tương thích linh kiện</a>
            <a href="admin.php?tab=reviews">Đánh giá sản phẩm</a>

            <div class="menu-heading" style="margin-top: 15px;">Thống Kê</div>
            <a href="thongke.php">Báo cáo Tổng quan</a>
            <a href="thongke_doanhthu.php">Thống kê Doanh thu</a>
            <a href="thongke_donhang.php">Thống kê Đơn hàng</a>
            <a href="thongke_banchay.php">Sản phẩm Bán chạy</a>

            <div class="menu-heading" style="margin-top: 15px;">Kho &amp; Danh Mục</div>
            <a href="danhmuc.php">Quản lý Danh mục</a>
            <a href="khohang.php">Tồn kho &amp; Cảnh báo</a>
            <div class="menu-heading" style="margin-top: 15px;">Tin nhắn từ người dùng</div>
            <a href="../tu-van/admintuvan.php">Phản hồi tư vấn</a>
            <a href="../phan-hoi/adminphanhoi.php">Phản hồi vấn đề hệ thống</a>
        </nav>
    </aside>

    <!-- 2. BÊN PHẢI: KHU VỰC HIỂN THỊ THÔNG TIN CHÍNH -->
    <main class="admin-main">
        <header class="main-header no-print">
            <div class="header-title">
                HỆ THỐNG QUẢN TRỊ KAIDO SHOP
            </div>
            <div class="topbar-actions">
                <a href="../index.php" target="_blank">🌐 Trang chủ Cửa hàng</a>
                <a href="../Taikhoan/dangxuat.php" style="color:#ef4444;">🚪 Đăng xuất</a>
            </div>
        </header>

        <div class="content-body">
            <!-- Bộ lọc kỳ báo cáo -->
            <div class="card-box">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                    <div>
                        <h2 style="margin:0 0 6px; font-size:20px;">Thống kê &amp; báo cáo doanh thu</h2>
                        <p class="muted" style="margin:0;">
                            Kỳ báo cáo: <strong>tháng <?= $month ?>/<?= $year ?></strong>. Doanh thu và sản phẩm bán ra chỉ tính đơn đã hoàn thành trong kỳ.
                        </p>
                    </div>
                </div>

                <form class="toolbar no-print" method="get">
                    <label>
                        Tháng
                        <select name="month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>>Tháng <?= $m ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label>
                        Năm
                        <input type="number" name="year" min="2000" max="2100" value="<?= $year ?>" required>
                    </label>
                    <button type="submit" class="btn-action btn-save">Xem báo cáo</button>
                    <button type="button" class="btn-action btn-print" onclick="window.print()">🖨️ In / Lưu PDF</button>
                </form>
            </div>

            <!-- Các thẻ chỉ số tổng quan (đã tận dụng class .stat-card của glassy.css) -->
            <div class="stat-grid">
                <div class="stat-card">
                    <span>Đơn tạo trong tháng</span>
                    <strong><?= (int)$created['total'] ?></strong>
                </div>
                <div class="stat-card">
                    <span>Đơn hoàn thành</span>
                    <strong><?= (int)$sales['completed'] ?></strong>
                </div>
                <div class="stat-card cancelled">
                    <span>Đơn bị hủy (tạo trong tháng)</span>
                    <strong><?= (int)$created['cancelled'] ?></strong>
                </div>
                <div class="stat-card">
                    <span>Sản phẩm đã bán</span>
                    <strong><?= (int)$totalItems ?></strong>
                </div>
                <div class="stat-card revenue">
                    <span>Doanh thu thực nhận</span>
                    <strong><?= h(tien($sales['revenue'])) ?></strong>
                </div>
            </div>

            <!-- Bảng Top sản phẩm bán chạy -->
            <div class="card-box">
                <h2 style="margin:0 0 6px; font-size:20px;">Sản phẩm bán chạy trong tháng</h2>
                <div style="overflow-x:auto;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width:80px;">Hạng</th>
                                <th>Tên sản phẩm</th>
                                <th class="number">Số lượng bán</th>
                                <th class="number">Doanh thu sản phẩm</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $index => $product): 
                                $rank = $index + 1;
                                $rankClass = $rank <= 3 ? "top-$rank" : "";
                            ?>
                                <tr>
                                    <td><span class="rank-badge <?= $rankClass ?>"><?= $rank ?></span></td>
                                    <td><strong><?= h($product['product_name']) ?></strong></td>
                                    <td class="number"><?= (int)$product['quantity'] ?></td>
                                    <td class="number"><strong style="color:#10b981;"><?= h(tien($product['revenue'])) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$products): ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; color:#94a3b8;">
                                        Chưa có sản phẩm từ đơn hoàn thành trong kỳ này.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <p class="muted" style="margin:18px 0 0; font-size:13px;">
                    * Ghi chú: Doanh thu là tổng giá trị đơn hoàn thành, chưa trừ chi phí hoặc hoàn tiền. Số đơn tạo và đơn hủy được tính theo ngày tạo đơn; đơn hoàn thành được tính theo ngày cập nhật trạng thái.
                </p>
            </div>
        </div>
    </main>

</body>
<script src="../js/slide.js"></script>
</html>