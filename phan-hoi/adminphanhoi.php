<?php
require_once __DIR__ . '/../admin/baove.php';

$states = ['new' => 'Mới nhận', 'reviewing' => 'Đang xem xét', 'resolved' => 'Đã xử lý'];
$categories = [
    'website_error'   => 'Lỗi website',
    'product_service' => 'Sản phẩm/dịch vụ',
    'feature_idea'    => 'Đề xuất tính năng',
    'other'           => 'Khác'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $state = (string)($_POST['status'] ?? '');
    if ($id > 0 && isset($states[$state])) {
        $st = $conn->prepare('UPDATE system_feedback SET status=? WHERE id=?');
        $st->bind_param('si', $state, $id);
        $st->execute();
        $st->close();
    }
    header('Location: adminphanhoi.php?status=' . rawurlencode($_GET['status'] ?? 'all') . '&updated=1');
    exit;
}

$filter = (string)($_GET['status'] ?? 'all');
if (!isset($states[$filter])) $filter = 'all';

try {
    $counts = ['all' => (int)$conn->query('SELECT COUNT(*) n FROM system_feedback')->fetch_assoc()['n']];
    foreach ($states as $key => $label) {
        $counts[$key] = (int)$conn->query("SELECT COUNT(*) n FROM system_feedback WHERE status='$key'")->fetch_assoc()['n'];
    }
    if ($filter === 'all') {
        $rows = $conn->query('SELECT * FROM system_feedback ORDER BY created_at DESC')->fetch_all(MYSQLI_ASSOC);
    } else {
        $st = $conn->prepare('SELECT * FROM system_feedback WHERE status=? ORDER BY created_at DESC');
        $st->bind_param('s', $filter);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
    }
    $tableError = '';
} catch (Throwable $e) {
    $rows = [];
    $counts = array_fill_keys(array_merge(['all'], array_keys($states)), 0);
    $tableError = 'Chưa có bảng dữ liệu. Hãy chạy tệp schema.sql trong thư mục phan-hoi bằng phpMyAdmin.';
}

// Đếm số đánh giá chưa đọc để hiển thị badge trên sidebar đồng bộ với admin.php
$rvChuaDoc = 0;
try {
    $rvChuaDoc = (int)$conn->query('SELECT COUNT(*) AS n FROM product_reviews WHERE is_read = 0')->fetch_assoc()['n'];
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý phản hồi hệ thống - Kaido Shop</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../image/kaidoLogomini.png">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: 'HarmonyOS Sans', system-ui, sans-serif;
            display: flex;
            min-height: 100vh;
        }
        .admin-sidebar {
            width: 235px;
            min-width: 235px;
            max-width: 450px;
            resize: horizontal;
            overflow: auto;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            z-index: 10;
        }
        .brand-logo-container {
            padding: 20px 15px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }
        .brand-logo-container img {
            max-width: 140px;
            height: auto;
            object-fit: contain;
            border-radius: 8px;
            transition: transform 0.3s;
        }
        .brand-logo-container img:hover { transform: scale(1.05); }
        .brand-name {
            font-size: 14px;
            font-weight: bold;
            margin-top: 8px;
            letter-spacing: 1px;
        }
        .admin-profile {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0861c3, #074997);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }
        .admin-info-text { font-size: 14px; line-height: 1.3; }
        .admin-info-text span { display: block; font-size: 12px; }
        .sidebar-menu { padding: 15px 10px; flex: 1; }
        .menu-heading {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: bold;
            padding: 10px 15px 5px;
            letter-spacing: 0.5px;
        }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 11px 16px;
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.25s ease;
        }
        .menu-count {
            display: inline-block;
            min-width: 20px;
            padding: 1px 7px;
            margin-left: auto;
            border-radius: 99px;
            background: #ef4444;
            color: #fff !important;
            font-size: 11px;
            font-weight: 700;
            text-align: center;
        }
        .admin-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }
        .main-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }
        .header-title { font-size: 18px; font-weight: bold; }
        .topbar-actions a {
            text-decoration: none;
            margin-left: 18px;
            font-size: 14px;
            font-weight: 600;
        }
        .content-body { padding: 24px; flex: 1; }
        .card-box { padding: 24px; }

        /* Filter pills & Badges */
        .filter-bar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
        .filter-bar a {
            padding: 7px 14px;
            border-radius: 99px;
            border: 1px solid var(--field-border);
            background: var(--paper);
            color: var(--ink) !important;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: transform 0.15s;
        }
        .filter-bar a.active {
            background: var(--primary-gradient) !important;
            color: #fff !important;
            border-color: transparent;
        }
        .status-pill {
            display: inline-block;
            padding: 4px 11px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .status-pill.new { background: rgba(239, 68, 68, 0.2); color: #ef4444 !important; }
        .status-pill.reviewing { background: rgba(245, 158, 11, 0.2); color: #d97706 !important; }
        .status-pill.resolved { background: rgba(16, 185, 129, 0.2); color: #059669 !important; }
        html[data-glassy-theme="dark"] .status-pill.new { color: #fca5a5 !important; }
        html[data-glassy-theme="dark"] .status-pill.reviewing { color: #fcd34d !important; }
        html[data-glassy-theme="dark"] .status-pill.resolved { color: #6ee7b7 !important; }

        .inline-form { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .inline-form select {
            padding: 6px 10px;
            border-radius: 8px;
            border: 1px solid var(--field-border);
            font-size: 13px;
        }
        .btn-action {
            padding: 7px 14px;
            border-radius: 8px;
            color: #fff !important;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid #10b981;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .msg-content { max-width: 420px; white-space: pre-wrap; word-break: break-word; line-height: 1.5; }
        .sub-meta { font-size: 12px; opacity: 0.8; }

        @media (max-width: 700px) {
            body { flex-direction: column; }
            .admin-sidebar { width: 100%; resize: none; }
            .content-body { padding: 12px; }
        }
    </style>
    <link rel="stylesheet" href="../css/glassy.css">
    <link rel="stylesheet" href="../css/darklight.css">
    <script src="../js/glassy-theme.js"></script>
</head>
<body class="glassy-page glassy-native-admin">

    <!-- 1. BÊN TRÁI: SIDEBAR ĐIỀU HƯỚNG ĐỒNG BỘ -->
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
                <span>Quản trị viên</span>
            </div>
        </div>

        <button type="button" class="glassy-theme-toggle">Chuyển giao diện</button>

        <nav class="sidebar-menu">
            <div class="menu-heading">Bảng Điều Khiển</div>
            <a href="../admin/admin.php?tab=products">Quản lý Sản phẩm</a>
            <a href="../admin/admin.php?tab=orders">Quản lý Đơn hàng</a>
            <a href="../admin/admin.php?tab=users">Quản lý Người dùng</a>
            <a href="../admin/admin.php?tab=reviews">Đánh giá sản phẩm<?php if ($rvChuaDoc > 0): ?><span class="menu-count"><?= (int)$rvChuaDoc ?></span><?php endif; ?></a>

            <div class="menu-heading" style="margin-top: 15px;">Thống Kê</div>
            <a href="../admin/thongke.php">Báo cáo Tổng quan</a>
            <a href="../admin/thongke_doanhthu.php">Thống kê Doanh thu</a>
            <a href="../admin/thongke_donhang.php">Thống kê Đơn hàng</a>
            <a href="../admin/thongke_banchay.php">Sản phẩm Bán chạy</a>

            <div class="menu-heading" style="margin-top: 15px;">Kho &amp; Danh Mục</div>
            <a href="../admin/danhmuc.php">Quản lý Danh mục</a>
            <a href="../admin/khohang.php">Tồn kho &amp; Cảnh báo</a>

            <div class="menu-heading" style="margin-top: 15px;">Tin nhắn từ người dùng</div>
            <a href="../tu-van/admintuvan.php">Phản hồi tư vấn</a>
            <a href="adminphanhoi.php" class="active">Phản hồi vấn đề hệ thống</a>
        </nav>
    </aside>

    <!-- 2. BÊN PHẢI: NỘI DUNG CHÍNH -->
    <main class="admin-main">
        <header class="main-header">
            <div class="header-title">HỆ THỐNG QUẢN TRỊ KAIDO SHOP</div>
            <div class="topbar-actions">
                <a href="../index.php" target="_blank">🌐 Trang chủ Cửa hàng</a>
                <a href="../Taikhoan/dangxuat.php" style="color:#ef4444;">🚪 Đăng xuất</a>
            </div>
        </header>

        <div class="content-body">
            <div class="card-box">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom: 16px;">
                    <div>
                        <h2 style="margin:0 0 4px; font-size:20px;">Phản hồi vấn đề hệ thống</h2>
                        <p class="sub-meta" style="margin:0;">Đọc và theo dõi các báo lỗi, góp ý tính năng từ khách hàng.</p>
                    </div>
                </div>

                <div class="filter-bar">
                    <a class="<?= $filter === 'all' ? 'active' : '' ?>" href="adminphanhoi.php">Tất cả (<?= $counts['all'] ?>)</a>
                    <?php foreach ($states as $key => $label): ?>
                        <a class="<?= $filter === $key ? 'active' : '' ?>" href="adminphanhoi.php?status=<?= h($key) ?>"><?= h($label) ?> (<?= $counts[$key] ?>)</a>
                    <?php endforeach; ?>
                </div>

                <?php if (isset($_GET['updated'])): ?>
                    <div class="alert-success" role="status">Đã cập nhật trạng thái phản hồi thành công!</div>
                <?php endif; ?>

                <?php if ($tableError): ?>
                    <div class="alert-error" role="alert"><?= h($tableError) ?></div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Người gửi</th>
                                    <th>Phân loại &amp; Nội dung</th>
                                    <th>Thời gian</th>
                                    <th>Trạng thái &amp; Xử lý</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$rows): ?>
                                <tr><td colspan="4" style="text-align:center;">Chưa có phản hồi nào trong mục này.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($row['full_name']) ?></strong>
                                        <div class="sub-meta"><?= h($row['email']) ?></div>
                                    </td>
                                    <td class="msg-content">
                                        <strong style="color:var(--accent) !important;"><?= h($categories[$row['category']] ?? $row['category']) ?></strong><br>
                                        <?= nl2br(h($row['message'])) ?>
                                    </td>
                                    <td><span class="sub-meta"><?= h(date('d/m/Y H:i', strtotime($row['created_at']))) ?></span></td>
                                    <td>
                                        <span class="status-pill <?= h($row['status']) ?>"><?= h($states[$row['status']]) ?></span>
                                        <form method="post" class="inline-form" action="adminphanhoi.php?status=<?= h($filter) ?>">
                                            <?= admin_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                            <select name="status" aria-label="Trạng thái phản hồi của <?= h($row['full_name']) ?>">
                                                <?php foreach ($states as $key => $label): ?>
                                                    <option value="<?= h($key) ?>" <?= $row['status'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn-action btn-save">Lưu</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>