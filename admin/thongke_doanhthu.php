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
$where = $from === null ? '' : ' AND o.created_at >= ? AND o.created_at < ?';

$stmt = $conn->prepare("SELECT COUNT(*) AS so_don, COALESCE(SUM(o.total_amount), 0) AS doanh_thu FROM orders o WHERE o.status = 'completed'" . $where);
if ($from !== null) $stmt->bind_param('ss', $from, $until);
$stmt->execute();
$tong = $stmt->get_result()->fetch_assoc();
$stmt->close();
$doanhThu = (float)$tong['doanh_thu'];
$soDon = (int)$tong['so_don'];
$trungBinh = $soDon > 0 ? $doanhThu / $soDon : 0;

$group = in_array($period, ['7d', '30d', 'month'], true)
    ? "DATE_FORMAT(o.created_at, '%d/%m')"
    : "DATE_FORMAT(o.created_at, '%m/%Y')";
$sql = "SELECT $group AS nhan, SUM(o.total_amount) AS tien FROM orders o WHERE o.status = 'completed'" . $where . " GROUP BY $group ORDER BY MIN(o.created_at) DESC LIMIT 12";
$stmt = $conn->prepare($sql);
if ($from !== null) $stmt->bind_param('ss', $from, $until);
$stmt->execute();
$bieudo = array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
$stmt->close();
$max = 0.0;
foreach ($bieudo as $item) $max = max($max, (float)$item['tien']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống kê Doanh thu - Kaido Admin</title>
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

        /* Thống kê phong cách admin */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 22px; }
        .stat-card {
            background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 18px; border-radius: 10px; text-align: center;
        }
        .stat-card span { font-size: 13px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card strong { display: block; font-size: 24px; margin-top: 6px; color: #34d399; }

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

        .tk-bars { display: grid; gap: 16px; margin-top: 15px; }
        .tk-bar-line { display: grid; grid-template-columns: 90px minmax(0,1fr) 140px; gap: 12px; align-items: center; font-size: 14px; }
        .tk-track { background: rgba(15, 23, 42, 0.6); border: 1px solid #334155; height: 18px; border-radius: 20px; overflow: hidden; }
        .tk-fill { background: linear-gradient(90deg, #ff416c, #ff4b2b); height: 100%; border-radius: 20px; }
        .tk-num { text-align: right; font-weight: 700; color: #34d399; }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .admin-sidebar { width: 100%; }
            .content-body { padding: 15px; }
            .tk-bar-line { grid-template-columns: 1fr 1fr; }
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
            <div class="header-title">THỐNG KÊ DOANH THU</div>
            <div class="topbar-actions">
                <a href="../index.php" target="_blank">🌐 Trang chủ Cửa hàng</a>
                <a href="../Taikhoan/dangxuat.php" style="color:#ef4444;">🚪 Đăng xuất</a>
            </div>
        </header>

        <div class="content-body">
            <div class="card-box">
                <p style="color:#94a3b8; margin-top:0;">Chỉ tính những đơn hàng đã hoàn thành.</p>
                
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
                        <span>Tổng doanh thu</span>
                        <strong><?= h(tien($doanhThu)) ?></strong>
                    </div>
                    <div class="stat-card">
                        <span>Đơn hoàn thành</span>
                        <strong style="color:#38bdf8;"><?= number_format($soDon,0,',','.') ?></strong>
                    </div>
                    <div class="stat-card">
                        <span>Trung bình mỗi đơn</span>
                        <strong style="color:#fbbf24;"><?= h(tien($trungBinh)) ?></strong>
                    </div>
                </div>
            </div>

            <div class="card-box">
                <h2 style="margin-top:0; font-size:18px; color:#f8fafc;">Biểu đồ doanh thu</h2>
                <p style="color:#94a3b8; font-size:13px;">Tối đa 12 ngày hoặc tháng có phát sinh doanh thu gần nhất trong khoảng lọc.</p>
                <?php if (!$bieudo): ?>
                    <p style="text-align:center; color:#94a3b8;">Chưa có đơn hoàn thành trong khoảng thời gian này.</p>
                <?php else: ?>
                    <div class="tk-bars">
                        <?php foreach ($bieudo as $item): $pct = $max > 0 ? (float)$item['tien']/$max*100 : 0; ?>
                            <div class="tk-bar-line">
                                <span style="color:#94a3b8;"><?= h($item['nhan']) ?></span>
                                <div class="tk-track"><div class="tk-fill" style="width:<?= round($pct,2) ?>%"></div></div>
                                <span class="tk-num"><?= h(tien($item['tien'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>