<?php
require_once __DIR__ . '/baove.php';
require_once __DIR__ . '/../donhang/trangthai.php';

$period = (string)($_GET['period'] ?? '30d');
if (!in_array($period, ['7d', '30d', 'month', 'year', 'all'], true)) $period = '30d';
$today = new DateTimeImmutable('today', new DateTimeZone('Asia/Ho_Chi_Minh'));
$start = match ($period) {
    '7d' => $today->modify('-6 days'),
    '30d' => $today->modify('-29 days'),
    'month' => $today->modify('first day of this month'),
    'year' => $today->setDate((int)$today->format('Y'), 1, 1),
    default => null
};
$from = $start ? $start->format('Y-m-d H:i:s') : null;
$until = $today->modify('+1 day')->format('Y-m-d H:i:s');
$where = $from === null ? '' : ' WHERE o.created_at >= ? AND o.created_at < ?';

$stmt = $conn->prepare('SELECT o.status, COUNT(*) AS sl FROM orders o' . $where . ' GROUP BY o.status');
if ($from !== null) $stmt->bind_param('ss', $from, $until);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$counts = ['pending'=>0,'confirmed'=>0,'shipping'=>0,'completed'=>0,'cancelled'=>0];
foreach ($rows as $row) if (isset($counts[$row['status']])) $counts[$row['status']] = (int)$row['sl'];
$total = array_sum($counts);
$processing = $counts['pending'] + $counts['confirmed'] + $counts['shipping'];
$rate = $total ? round(100*$counts['completed']/$total,1) : 0;

$stmt = $conn->prepare('SELECT o.id, o.recipient_name, o.created_at, o.status, o.total_amount FROM orders o' . $where . ' ORDER BY o.created_at DESC, o.id DESC LIMIT 15');
if ($from !== null) $stmt->bind_param('ss', $from, $until);
$stmt->execute();
$recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống kê Đơn hàng - Kaido Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../image/kaidoLogomini.png">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0;
            background: #0f172a; color: #f1f5f9;
            font-family: 'HarmonyOS Sans', system-ui, sans-serif;
            display: flex; min-height: 100vh;
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
            padding: 20px 15px; text-align: center; background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .brand-logo-container img { max-width: 140px; height: auto; border-radius: 8px; }
        .brand-name { font-size: 14px; font-weight: bold; color: #94a3b8; margin-top: 8px; letter-spacing: 1px; }

        .admin-profile {
            padding: 15px 20px; display: flex; align-items: center; gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .avatar-circle {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, #ff416c, #ff4b2b);
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; font-size: 18px; color: white;
            box-shadow: 0 2px 8px rgba(255, 65, 108, 0.4);
        }

        .sidebar-menu { padding: 15px 10px; flex: 1; }
        .menu-heading { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: bold; padding: 10px 15px 5px; letter-spacing: 0.5px; }
        .sidebar-menu a {
            display: flex; align-items: center; padding: 12px 16px; color: #cbd5e1;
            text-decoration: none; border-radius: 8px; margin-bottom: 6px; font-weight: 500; font-size: 14px; transition: all 0.25s ease;
        }
        .sidebar-menu a:hover { background: rgba(255, 255, 255, 0.06); color: #fff; transform: translateX(4px); }
        .sidebar-menu a.active { background: linear-gradient(90deg, #ff416c, #ff4b2b); color: white; box-shadow: 0 4px 12px rgba(255, 65, 108, 0.3); }

        /* 2. KHU VỰC CHÍNH */
        .admin-main { flex: 1; display: flex; flex-direction: column; overflow-x: hidden; background: #0f172a; }
        .main-header {
            height: 65px; background: #1e293b; border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex; align-items: center; justify-content: space-between; padding: 0 30px;
        }
        .header-title { font-size: 18px; font-weight: bold; color: #f8fafc; }
        .topbar-actions a { color: #94a3b8; text-decoration: none; margin-left: 18px; font-size: 14px; }
        .topbar-actions a:hover { color: #ff416c; }

        .content-body { padding: 30px; flex: 1; }
        .card-box {
            background: #1e293b; border-radius: 12px; padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.05); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
            margin-bottom: 22px;
        }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 15px; margin-bottom: 22px; }
        .stat-card {
            background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 18px; border-radius: 10px; text-align: center;
        }
        .stat-card span { font-size: 13px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card strong { display: block; font-size: 24px; margin-top: 6px; color: #38bdf8; }

        .filter-form {
            display: flex; gap: 15px; align-items: center; flex-wrap: wrap; background: rgba(0,0,0,0.2);
            padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05);
        }
        .filter-form select {
            padding: 7px 12px; border-radius: 6px; border: 1px solid #334155; background: #0f172a; color: white; font-size: 14px;
        }
        .btn-submit {
            padding: 7px 16px; background: linear-gradient(90deg, #ff416c, #ff4b2b); color: white;
            border: none; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px;
        }

        .tk-bars { display: grid; gap: 18px; margin-top: 15px; }
        .tk-barline { display: grid; grid-template-columns: 160px minmax(0,1fr) 110px; gap: 12px; align-items: center; font-size: 14px; }
        .tk-track { height: 18px; border-radius: 20px; background: rgba(15, 23, 42, 0.6); border: 1px solid #334155; overflow: hidden; }
        .tk-fill { height: 100%; border-radius: 20px; }

        table.admin-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 15px; }
        table.admin-table th, table.admin-table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.06); font-size: 14px; }
        table.admin-table th { background: rgba(15, 23, 42, 0.6); color: #94a3b8; font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
        table.admin-table tr:hover { background: rgba(255, 255, 255, 0.02); }

        .tk-pill { display: inline-block; border-radius: 20px; padding: 4px 10px; color: #fff; font-size: 12px; font-weight: bold; }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .admin-sidebar { width: 100%; }
            .content-body { padding: 15px; }
            .tk-barline { grid-template-columns: 1fr 1fr; }
            .tk-track { grid-column: 1/-1; grid-row: 2; }
        }
    </style>
    <link rel="stylesheet" href="../css/glassy.css">
    <script src="../js/glassy-theme.js"></script>
</head>
<body class="glassy-page glassy-native-admin">

    <!-- 1. SIDEBAR TRÁI -->
    <aside class="admin-sidebar">
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
                <span style="color:#64748b; font-size:12px; display:block;">Quản trị viên</span>
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

    <!-- 2. MAIN CONTENT BÊN PHẢI -->
    <main class="admin-main">
        <header class="main-header">
            <div class="header-title">THỐNG KÊ SỐ LƯỢNG ĐƠN HÀNG</div>
            <div class="topbar-actions">
                <a href="../index.php" target="_blank">🌐 Trang chủ Cửa hàng</a>
                <a href="../Taikhoan/dangxuat.php" style="color:#ef4444;">🚪 Đăng xuất</a>
            </div>
        </header>

        <div class="content-body">
            <div class="card-box">
                <p style="color:#94a3b8; margin-top:0;">Thống kê tất cả trạng thái, bao gồm đơn đang xử lý và đơn đã hủy.</p>
                
                <form method="get" class="filter-form">
                    <label style="color:#94a3b8; font-size:13px;">Khoảng thời gian:
                        <select name="period">
                            <?php foreach (['7d'=>'7 ngày','30d'=>'30 ngày','month'=>'Tháng này','year'=>'Năm nay','all'=>'Tất cả'] as $key=>$name): ?>
                                <option value="<?= h($key) ?>" <?= $period === $key ? 'selected' : '' ?>><?= h($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="btn-submit">Lọc dữ liệu</button>
                </form>

                <div class="stats-grid">
                    <div class="stat-card">
                        <span>Tổng đơn hàng</span>
                        <strong><?= number_format($total,0,',','.') ?></strong>
                    </div>
                    <div class="stat-card">
                        <span>Đang xử lý</span>
                        <strong style="color:#fbbf24;"><?= number_format($processing,0,',','.') ?></strong>
                    </div>
                    <div class="stat-card">
                        <span>Đã hoàn thành</span>
                        <strong style="color:#34d399;"><?= number_format($counts['completed'],0,',','.') ?></strong>
                    </div>
                    <div class="stat-card">
                        <span>Đã hủy</span>
                        <strong style="color:#f87171;"><?= number_format($counts['cancelled'],0,',','.') ?></strong>
                    </div>
                    <div class="stat-card">
                        <span>Tỷ lệ hoàn thành</span>
                        <strong style="color:#38bdf8;"><?= $rate ?>%</strong>
                    </div>
                </div>
            </div>

            <div class="card-box" style="margin-bottom:22px;">
                <h2 style="margin-top:0; font-size:18px; color:#f8fafc;">Đơn hàng theo trạng thái</h2>
                <div class="tk-bars">
                    <?php foreach ($counts as $status=>$qty): $pct = $total ? $qty*100/$total : 0; ?>
                        <div class="tk-barline">
                            <span style="color:#94a3b8;"><?= h(tenTrangThai($status)) ?></span>
                            <div class="tk-track"><div class="tk-fill" style="width:<?= round($pct,2) ?>%;background:<?= h(mauTrangThai($status)) ?>"></div></div>
                            <strong style="color:#f8fafc; font-size:13px; text-align:right;"><?= $qty ?> (<?= round($pct,1) ?>%)</strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card-box">
                <h2 style="margin-top:0; font-size:18px; color:#f8fafc;">15 đơn hàng gần nhất</h2>
                <div style="overflow-x:auto;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Khách hàng</th>
                                <th>Ngày đặt</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$recent): ?>
                                <tr><td colspan="5" style="text-align:center; color:#94a3b8;">Chưa có đơn hàng trong khoảng thời gian này.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($recent as $don): ?>
                                <tr>
                                    <td><strong style="color:#38bdf8;"><?= h(maDon((int)$don['id'])) ?></strong></td>
                                    <td><?= h($don['recipient_name']) ?></td>
                                    <td><span style="color:#94a3b8; font-size:13px;"><?= h(date('d/m/Y H:i',strtotime($don['created_at']))) ?></span></td>
                                    <td><strong style="color:#34d399;"><?= h(tien($don['total_amount'])) ?></strong></td>
                                    <td><span class="tk-pill" style="background:<?= h(mauTrangThai($don['status'])) ?>"><?= h(tenTrangThai($don['status'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>