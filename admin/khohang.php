<?php
require_once __DIR__ . '/baove.php';

$threshold = filter_input(INPUT_GET, 'nguong', FILTER_VALIDATE_INT);
if ($threshold === false || $threshold === null || $threshold < 1 || $threshold > 100) {
    $threshold = 5;
}
$filter = (string)($_GET['loc'] ?? 'all');
if (!in_array($filter, ['all', 'low', 'out', 'ok'], true)) {
    $filter = 'all';
}
$all = $conn->query('SELECT id, sku, product_name, stock_qty, price, status FROM products ORDER BY stock_qty ASC, id DESC')->fetch_all(MYSQLI_ASSOC);
$counts = ['total' => count($all), 'low' => 0, 'out' => 0, 'ok' => 0];
$shown = [];
foreach ($all as $product) {
    $stock = (int)$product['stock_qty'];
    $state = $stock === 0 ? 'out' : ($stock <= $threshold ? 'low' : 'ok');
    $counts[$state]++;
    if ($filter === 'all' || $filter === $state) {
        $product['_state'] = $state;
        $shown[] = $product;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Tồn Kho - Kaido Admin</title>
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
            width: 260px; background: #1e293b;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            display: flex; flex-direction: column; flex-shrink: 0; z-index: 10;
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

        /* 2. KHU VỰC HIỂN THỊ NỘI DUNG MAIN */
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
        }

        /* Thống kê dạng Thẻ (Cards Grid) */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card {
            background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 18px; border-radius: 10px; text-align: center;
        }
        .stat-card span { font-size: 13px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card strong { display: block; font-size: 26px; margin-top: 6px; color: #f8fafc; }

        /* Filter Form */
        .filter-form {
            display: flex; gap: 15px; align-items: center; flex-wrap: wrap; background: rgba(0,0,0,0.2);
            padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05);
        }
        .filter-form input, .filter-form select {
            padding: 7px 12px; border-radius: 6px; border: 1px solid #334155; background: #0f172a; color: white; font-size: 14px;
        }
        .btn-submit {
            padding: 7px 16px; background: linear-gradient(90deg, #ff416c, #ff4b2b); color: white;
            border: none; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px;
        }

        /* Table */
        table.admin-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; }
        table.admin-table th, table.admin-table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.06); font-size: 14px; }
        table.admin-table th { background: rgba(15, 23, 42, 0.6); color: #94a3b8; font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
        table.admin-table tr:hover { background: rgba(255, 255, 255, 0.02); }

        /* Badges */
        .tag { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .tag.out { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .tag.low { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        .tag.ok { background: rgba(16, 185, 129, 0.2); color: #34d399; }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .admin-sidebar { width: 100%; }
            .content-body { padding: 15px; }
        }
    </style>
</head>
<body>

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
                <?= strtoupper(substr(h($admin_username), 0, 1)) ?>
            </div>
            <div class="admin-info-text">
                <strong><?= h($admin_username) ?></strong>
                <span style="color:#64748b; font-size:12px; display:block;">Quản trị viên</span>
            </div>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-heading">Bảng Điều Khiển</div>
            <a href="admin.php?tab=products">Quản lý Sản phẩm</a>
            <a href="admin.php?tab=orders">Quản lý Đơn hàng</a>
            <a href="admin.php?tab=users">Quản lý Người dùng</a>

            <div class="menu-heading" style="margin-top: 15px;">Kho & Danh Mục</div>
            <a href="danhmuc.php">Quản lý Danh mục</a>
            <a href="khohang.php" class="active">Tồn kho & Cảnh báo</a>
        </nav>
    </aside>

    <!-- 2. MAIN CONTENT BÊN PHẢI -->
    <main class="admin-main">
        <header class="main-header">
            <div class="header-title">QUẢN LÝ KHO & CẢNH BÁO TỒN KHO</div>
            <div class="topbar-actions" style="display: flex; align-items: center; gap: 10px;">
                <!-- NÚT BẤM CHUYỂN ĐỔI DARK / LIGHT MODE -->
                <button id="themeToggle" class="theme-toggle-btn" onclick="toggleTheme()" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 6px 12px; border-radius: 6px; cursor: pointer;">
                    🌙 Dark
                </button>
            <div class="topbar-actions">
                <a href="../index.php" target="_blank">🌐 Trang chủ Cửa hàng</a>
                <a href="../Taikhoan/dangxuat.php" style="color:#ef4444;">🚪 Đăng xuất</a>
            </div>
        </header>

        <div class="content-body">
            <div class="card-box">
                
                <!-- Card Thống kê Tồn kho -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <span>Tổng sản phẩm</span>
                        <strong><?= $counts['total'] ?></strong>
                    </div>
                    <div class="stat-card" style="border-color: rgba(245, 158, 11, 0.3);">
                        <span style="color:#fbbf24;">⚠️ Sắp hết hàng</span>
                        <strong style="color:#fbbf24;"><?= $counts['low'] ?></strong>
                    </div>
                    <div class="stat-card" style="border-color: rgba(239, 68, 68, 0.3);">
                        <span style="color:#f87171;">❌ Hết hàng</span>
                        <strong style="color:#f87171;"><?= $counts['out'] ?></strong>
                    </div>
                    <div class="stat-card" style="border-color: rgba(16, 185, 129, 0.3);">
                        <span style="color:#34d399;">✅ Tồn kho ổn định</span>
                        <strong style="color:#34d399;"><?= $counts['ok'] ?></strong>
                    </div>
                </div>

                <!-- Thanh Bộ Lọc & Cấu Hình Ngưỡng -->
                <form method="get" class="filter-form">
                    <label>
                        Ngưỡng cảnh báo: ≤ 
                        <input type="number" name="nguong" min="1" max="100" required value="<?= $threshold ?>" style="width:70px;">
                    </label>
                    
                    <label>
                        Lọc xem: 
                        <select name="loc">
                            <?php foreach (['all'=>'Tất cả sản phẩm','low'=>'Sắp hết hàng','out'=>'Đã hết hàng','ok'=>'Tồn kho bình thường'] as $value=>$title): ?>
                                <option value="<?= h($value) ?>" <?= $filter===$value?'selected':'' ?>><?= h($title) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <button type="submit" class="btn-submit">Áp dụng lọc</button>
                </form>

                <!-- Bảng Danh Sách Sản Phẩm Trong Kho -->
                <div style="overflow-x:auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>SKU</th>
                                <th>Sản phẩm</th>
                                <th>Giá bán (VNĐ)</th>
                                <th>Số lượng kho</th>
                                <th>Mức cảnh báo</th>
                                <th>Trạng thái bán</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($shown as $p): ?>
                            <tr>
                                <td><?= (int)$p['id'] ?></td>
                                <td><code style="color:#38bdf8;"><?= h($p['sku']) ?></code></td>
                                <td><strong><?= h($p['product_name']) ?></strong></td>
                                <td><?= number_format((float)$p['price'], 0, ',', '.') ?> đ</td>
                                <td><strong style="font-size: 15px;"><?= (int)$p['stock_qty'] ?></strong></td>
                                <td>
                                    <?php $labels=['out'=>'❌ Hết hàng','low'=>'⚠️ Sắp hết hàng','ok'=>'✅ Bình thường']; ?>
                                    <span class="tag <?= h($p['_state']) ?>"><?= h($labels[$p['_state']]) ?></span>
                                </td>
                                <td>
                                    <span style="color: <?= $p['status'] === 'active' ? '#34d399' : '#94a3b8' ?>; font-size:13px;">
                                        <?= h($p['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="suasanpham.php?id=<?= (int)$p['id'] ?>" style="color:#60a5fa; text-decoration:none; font-weight:500;">✏️ Cập nhật kho</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$shown): ?>
                            <tr><td colspan="8" style="text-align:center; color:#94a3b8;">Không có sản phẩm nào phù hợp với bộ lọc.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
<script src="../js/slide.js"></script>
</html>