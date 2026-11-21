<?php
// Đặt tại: admin/quanly_tuongthich.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/baove.php';                  // kiểm tra quyền admin của bạn
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../donhang/trangthai.php';   // h(), dhToken(), dhCheckToken()

$thongBao = ''; $loi = '';
// SỬA: status='active'; dùng dhToken/dhCheckToken (không phụ thuộc hàm admin_csrf_* của người kia)
$products = $conn->query("SELECT id,product_name FROM products WHERE status='active' ORDER BY product_name,id")->fetch_all(MYSQLI_ASSOC);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dhCheckToken();
    if (($_POST['action'] ?? '') === 'delete') {
        $rid = filter_input(INPUT_POST, 'rid', FILTER_VALIDATE_INT) ?: 0;
        if ($rid > 0) {
            $st = $conn->prepare('DELETE FROM tuong_thich_linh_kien WHERE id=?');
            $st->bind_param('i', $rid); $st->execute(); $st->close();
        }
        header('Location: quanly_tuongthich.php?del=1'); exit;
    }
    $a = filter_input(INPUT_POST, 'a', FILTER_VALIDATE_INT) ?: 0;
    $b = filter_input(INPUT_POST, 'b', FILTER_VALIDATE_INT) ?: 0;
    $verdict = (string)($_POST['ket_qua'] ?? '');
    $note = trim((string)($_POST['ghi_chu'] ?? ''));
    if (!$a || !$b || $a === $b || !in_array($verdict, ['tuong_thich', 'khong_tuong_thich', 'chua_xac_dinh'], true) || mb_strlen($note) > 500) {
        $loi = 'Thông tin không hợp lệ.';
    } else {
        try {
            $st = $conn->prepare('SELECT COUNT(*) AS n FROM products WHERE id IN (?,?)');
            $st->bind_param('ii', $a, $b); $st->execute(); $valid = (int)$st->get_result()->fetch_assoc()['n'] === 2; $st->close();
            if (!$valid) throw new RuntimeException('Sản phẩm không tồn tại.');
            $lo = min($a, $b); $hi = max($a, $b);
            $st = $conn->prepare('INSERT INTO tuong_thich_linh_kien (product_a_id,product_b_id,ket_qua,ghi_chu) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE ket_qua=VALUES(ket_qua),ghi_chu=VALUES(ghi_chu),updated_at=CURRENT_TIMESTAMP');
            $st->bind_param('iiss', $lo, $hi, $verdict, $note); $st->execute(); $st->close();
            header('Location: quanly_tuongthich.php?ok=1'); exit;
        } catch (Throwable $e) { error_log($e->getMessage()); $loi = 'Không lưu được quy tắc tương thích.'; }
    }
}
if (isset($_GET['ok'])) $thongBao = 'Đã cập nhật kết quả tương thích.';
if (isset($_GET['del'])) $thongBao = 'Đã xóa quy tắc tương thích.';

// Chọn sẵn cặp (bấm "Sửa" ở bảng dưới): nạp kết luận cũ + thông số hai sản phẩm để tham khảo
$selA = filter_input(INPUT_GET, 'a', FILTER_VALIDATE_INT) ?: 0;
$selB = filter_input(INPUT_GET, 'b', FILTER_VALIDATE_INT) ?: 0;
$selKq = 'chua_xac_dinh'; $selNote = ''; $selSpecs = []; $selNames = [];
if ($selA > 0 && $selB > 0 && $selA !== $selB) {
    $lo = min($selA, $selB); $hi = max($selA, $selB);
    $st = $conn->prepare('SELECT ket_qua,ghi_chu FROM tuong_thich_linh_kien WHERE product_a_id=? AND product_b_id=?');
    $st->bind_param('ii', $lo, $hi); $st->execute();
    if ($r = $st->get_result()->fetch_assoc()) { $selKq = $r['ket_qua']; $selNote = $r['ghi_chu']; }
    $st->close();
    $st = $conn->prepare('SELECT id,product_name FROM products WHERE id IN (?,?)');
    $st->bind_param('ii', $selA, $selB); $st->execute();
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $selNames[(int)$r['id']] = $r['product_name'];
    $st->close();
    $st = $conn->prepare('SELECT product_id,spec_name,spec_value FROM product_specs WHERE product_id IN (?,?) ORDER BY spec_name');
    $st->bind_param('ii', $selA, $selB); $st->execute();
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $selSpecs[(int)$r['product_id']][] = $r;
    $st->close();
} else { $selA = $selB = 0; }
$rules = $conn->query('SELECT t.id,t.product_a_id,t.product_b_id,t.ket_qua,t.ghi_chu,t.updated_at,p1.product_name AS a,p2.product_name AS b FROM tuong_thich_linh_kien t LEFT JOIN products p1 ON p1.id=t.product_a_id LEFT JOIN products p2 ON p2.id=t.product_b_id ORDER BY t.updated_at DESC LIMIT 100')->fetch_all(MYSQLI_ASSOC);
// Số đánh giá chưa đọc để hiện ở menu (giống admin.php)
$rvChuaDoc = 0;
try { $rvChuaDoc = (int)$conn->query('SELECT COUNT(*) AS n FROM product_reviews WHERE is_read = 0')->fetch_assoc()['n']; } catch (Throwable $e) {}
$nhan = ['tuong_thich' => 'Tương thích', 'khong_tuong_thich' => 'Không tương thích', 'chua_xac_dinh' => 'Chưa xác định'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý tương thích - Kaido Shop</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../image/kaidoLogomini.png">
    <style>
        /* === BỐ CỤC CHUNG KIỂU ADMINLTE DÙNG MÀU RIÊNG KAIDO === */
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

        /* Ảnh Logo Thương Hiệu ở Góc Trên Sidebar */
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

        /* Thông tin Admin trên Sidebar */
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

        /* Menu Sidebar (Cột Điều Hướng Đa Nhiệm) */
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

        /* Top Header Bar */
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

        /* Work Area Container */
        .content-body {
            padding: 30px;
            flex: 1;
        }

        /* Card Wrapper dạng AdminLTE màu Kaido */
        .card-box {
            background: #1e293b;
            border-radius: 12px;
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }

        /* Styles Bảng Dữ Liệu */
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

        /* Form Inputs & Buttons */
        .form-edit input {
            padding: 7px 10px;
            border-radius: 6px;
            border: 1px solid #334155;
            background: #0f172a;
            color: white;
            width: 100px;
        }
        .btn-action {
            padding: 6px 14px;
            border-radius: 6px;
            text-decoration: none;
            color: white;
            font-size: 13px;
            font-weight: 500;
            margin-right: 4px;
            border: none;
            cursor: pointer;
            display: inline-block;
            transition: opacity 0.2s;
        }
        .btn-save { background: #10b981; }
        .btn-delete { background: #ef4444; }
        .btn-action:hover { opacity: 0.85; }

        /* Notification Alerts */
        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid #10b981;
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #34d399;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #fca5a5;
        }

        .inline-form { display: inline; }
        .order-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            color: #fff;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }
        .order-form select, .order-form input[type="text"] {
            padding: 7px 10px;
            border-radius: 6px;
            border: 1px solid #334155;
            background: #0f172a;
            color: white;
            margin: 0 4px 6px 0;
            font-size: 13px;
        }
        .order-form input[type="text"] { width: 140px; }
        .order-sub { color: #94a3b8; font-size: 12px; }

        /* [ĐÁNH GIÁ] */
        .rv-badge-new { display:inline-block; padding:2px 8px; border-radius:99px; background:#ef4444; color:#fff; font-size:11px; font-weight:700; margin-left:6px; }
        .menu-count { display:inline-block; min-width:20px; padding:1px 7px; margin-left:auto; border-radius:99px; background:#ef4444; color:#fff; font-size:11px; font-weight:700; text-align:center; }
        .rv-filter a { display:inline-block; padding:7px 14px; margin-right:6px; border-radius:99px; border:1px solid #334155; font-size:13px; text-decoration:none; color:inherit; }
        .rv-filter a.on { background:linear-gradient(90deg,#ff416c,#ff4b2b); color:#fff; border-color:transparent; }
        .rv-thumbs { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
        .rv-thumbs img { width:64px; height:64px; object-fit:cover; border-radius:8px; border:1px solid #334155; }
        .rv-thumbs video { width:150px; height:84px; border-radius:8px; background:#000; }
        .rv-row-new { background: rgba(239,68,68,.07); }

        /* Responsive Mobile Layout */
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .admin-sidebar { width: 100%; }
            .main-header { padding: 0 15px; }
            .content-body { padding: 15px; }
        }
    </style>
<link rel="stylesheet" href="../css/glassy.css">
<link rel="stylesheet" href="../css/darklight.css">
<script src="../js/glassy-theme.js"></script>
</head>
<body class="glassy-page glassy-native-admin">

    <!-- 1. BÊN TRÁI: SIDEBAR ĐA NHIỆM PHONG CÁCH ADMINLTE -->
    <aside class="admin-sidebar">
        <!-- Ảnh Logo Thương Hiệu ở góc trên cùng -->
        <div class="brand-logo-container">
            <a href="../index.php">
                <img src="../image/logo.png" alt="Logo Kaido Shop" onerror="this.onerror=null; this.src='../image/kaidologo.jpg';">
            </a>
            <div class="brand-name">KAIDO ADMIN HUB</div>
        </div>

        <!-- Thông tin Tài khoản Admin -->
        <div class="admin-profile">
            <div class="avatar-circle">
                <?= strtoupper(substr(h($admin_username), 0, 1)) ?>
            </div>
            <div class="admin-info-text">
                <strong><?= h($admin_username) ?></strong>
                <span>Quản trị viên</span>
            </div>
        </div>

        <!-- Menu Các Tab Đa Nhiệm & Quản Lý -->
        <button type="button" class="glassy-theme-toggle">Chuyển giao diện</button>
        <nav class="sidebar-menu">
            <div class="menu-heading">Bảng Điều Khiển</div>
            <a href="admin.php?tab=products">Quản lý Sản phẩm</a>
            <a href="admin.php?tab=orders">Quản lý Đơn hàng</a>
            <a href="admin.php?tab=users">Quản lý Người dùng</a>
            <a href="quanly_tuongthich.php">Tương thích linh kiện</a>
            <a href="admin.php?tab=reviews">Đánh giá sản phẩm<?php if ($rvChuaDoc > 0): ?><span class="menu-count"><?= (int)$rvChuaDoc ?></span><?php endif; ?></a>

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

    <main class="admin-main">
        <!-- Header Thanh Trên Cùng -->
        <header class="main-header">
            <div class="header-title">
                HỆ THỐNG QUẢN TRỊ KAIDO SHOP
            </div>
            <div class="topbar-actions" style="display: flex; align-items: center; gap: 10px;">
                <!-- NÚT BẤM CHUYỂN ĐỔI DARK / LIGHT MODE -->

            <div class="topbar-actions">
                <a href="../index.php" target="_blank">🌐 Trang chủ Cửa hàng</a>
                <a href="../Taikhoan/dangxuat.php" style="color:#ef4444;">🚪 Đăng xuất</a>
            </div>
        </header>

        <div class="content-body">
            <div class="card-box">
                <h2 style="margin-top:0; font-size:20px; margin-bottom:20px;">Quản lý tương thích linh kiện</h2>
                <?php if ($thongBao): ?><div class="alert-success"><?= h($thongBao) ?></div><?php endif; ?>
                <?php if ($loi): ?><div class="alert-error"><?= h($loi) ?></div><?php endif; ?>

                <form method="post" id="form" class="kaido-form" style="margin-bottom:24px;">
                    <input type="hidden" name="csrf_token" value="<?= h(dhToken()) ?>">
                    <label>Linh kiện thứ nhất<select name="a" required><option value="">Chọn sản phẩm</option><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $selA === (int)$p['id'] ? 'selected' : '' ?>><?= h($p['product_name']) ?></option><?php endforeach; ?></select></label>
                    <label>Linh kiện thứ hai<select name="b" required><option value="">Chọn sản phẩm</option><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $selB === (int)$p['id'] ? 'selected' : '' ?>><?= h($p['product_name']) ?></option><?php endforeach; ?></select></label>
                    <label>Kết luận<select name="ket_qua" required><?php foreach ($nhan as $ma => $ten): ?><option value="<?= h($ma) ?>" <?= $selKq === $ma ? 'selected' : '' ?>><?= h($ten) ?></option><?php endforeach; ?></select></label>
                    <label style="flex:1; min-width:260px;">Điều kiện và giải thích<textarea name="ghi_chu" rows="3" maxlength="500" placeholder="Ví dụ: Kiểm tra điện áp, dòng tải, giao tiếp, chân cắm và điều kiện sử dụng."><?= h($selNote) ?></textarea></label>
                    <button class="btn-action btn-save" type="submit" style="padding:10px 18px;">Lưu kết quả</button>
                </form>

                <?php if ($selA): ?>
                <h3>Thông số để đối chiếu</h3>
                <div style="display:flex; gap:14px; flex-wrap:wrap; margin-bottom:24px;">
                    <?php foreach ([$selA, $selB] as $sid): ?>
                    <div style="flex:1; min-width:260px;">
                        <strong><?= h($selNames[$sid] ?? '') ?></strong>
                        <?php if (empty($selSpecs[$sid])): ?>
                            <p class="order-sub">Chưa có thông số kỹ thuật (thêm ở mục Thông số của sản phẩm).</p>
                        <?php else: ?>
                            <table class="admin-table"><tbody>
                            <?php foreach ($selSpecs[$sid] as $s): ?>
                                <tr><td><?= h($s['spec_name']) ?></td><td><strong><?= h($s['spec_value']) ?></strong></td></tr>
                            <?php endforeach; ?>
                            </tbody></table>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <h3>Các cặp đã thiết lập</h3>
                <div style="overflow-x:auto;">
                    <table class="admin-table">
                        <thead><tr><th>Linh kiện A</th><th>Linh kiện B</th><th>Kết luận</th><th>Ghi chú</th><th>Cập nhật</th><th>Hành động</th></tr></thead>
                        <tbody>
                        <?php foreach ($rules as $r): ?>
                            <tr>
                                <td><?= h($r['a'] ?? 'Đã xóa') ?></td>
                                <td><?= h($r['b'] ?? 'Đã xóa') ?></td>
                                <td><?= h($nhan[$r['ket_qua']] ?? $r['ket_qua']) ?></td>
                                <td><?= h($r['ghi_chu']) ?></td>
                                <td><span class="order-sub"><?= h($r['updated_at']) ?></span></td>
                                <td style="white-space:nowrap;">
                                    <a class="btn-action" style="background:#3b82f6;" href="quanly_tuongthich.php?a=<?= (int)$r['product_a_id'] ?>&amp;b=<?= (int)$r['product_b_id'] ?>#form">Sửa</a>
                                    <form class="inline-form" method="post" onsubmit="return confirm('Xóa quy tắc này?');">
                                        <input type="hidden" name="csrf_token" value="<?= h(dhToken()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="rid" value="<?= (int)$r['id'] ?>">
                                        <button class="btn-action btn-delete" type="submit">Xóa</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rules): ?>
                            <tr><td colspan="6" style="text-align:center; color:#94a3b8;">Chưa có quy tắc nào. Chọn hai linh kiện ở form phía trên rồi bấm Lưu.</td></tr>
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
