<?php
require_once __DIR__ . '/baove.php';   // PHẢI nằm đầu tiên: chặn người chưa đăng nhập / không phải admin[cite: 1]
require_once __DIR__ . '/../donhang/trangthai.php'; // tenTrangThai(), mauTrangThai(), tien(), maDon()[cite: 1]
require_once __DIR__ . '/../sanpham/danhgia_helper.php'; // [ĐÁNH GIÁ] rv_stars(), rv_media_url(), rv_upload_dir()

/* ------------------------------------------------------------------
   XỬ LÝ XÓA sản phẩm (chỉ nhận POST + CSRF, không dùng link GET nữa)[cite: 1]
------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    admin_csrf_check();
    $product_id = (int)($_POST['id'] ?? 0);
    if ($product_id <= 0) {
        header('Location: admin.php?tab=products&msg=invalid');
        exit;
    }
    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare('DELETE FROM product_specs WHERE product_id = ?');
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();
        if ($deleted !== 1) {
            throw new RuntimeException('Không tìm thấy sản phẩm.');
        }
        $conn->commit();
        header('Location: admin.php?tab=products&msg=deleted');
    } catch (Throwable $e) {
        $conn->rollback();
        error_log($e->getMessage());
        header('Location: admin.php?tab=products&msg=delete_failed');
    }
    exit;
}

/* ------------------------------------------------------------------
   XỬ LÝ CẬP NHẬT giá / tồn kho[cite: 1]
------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    admin_csrf_check();
    $id        = (int)($_POST['id'] ?? 0);
    $priceText = trim((string)($_POST['price'] ?? ''));
    $stockText = trim((string)($_POST['stock_qty'] ?? ''));
    $priceOK   = (bool)preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $priceText);
    $stockOK   = filter_var($stockText, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $price     = $priceOK ? (float)$priceText : 0;
    $stock_qty = $stockOK !== false ? (int)$stockOK : 0;

    if ($id <= 0 || !$priceOK || $stockOK === false) {
        header('Location: admin.php?tab=products&msg=invalid');
        exit;
    }
    $stmt = $conn->prepare('UPDATE products SET price = ?, stock_qty = ?, updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('dii', $price, $stock_qty, $id);
    $stmt->execute();
    $stmt->close();

    header('Location: admin.php?tab=products&msg=updated');
    exit;
}

/* ------------------------------------------------------------------
   XỬ LÝ ĐỔI TRẠNG THÁI ĐƠN HÀNG (chỉ nhận POST + CSRF)[cite: 1]
------------------------------------------------------------------- */
$duocPhep = [
    'pending'   => ['confirmed', 'cancelled'],
    'confirmed' => ['shipping', 'cancelled'],
    'shipping'  => ['completed'],
    'completed' => [],
    'cancelled' => [],
];

// Admin xác nhận tay đã nhận tiền chuyển khoản VietQR (khi chưa có webhook)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    admin_csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare('SELECT status, payment_method, payment_status FROM orders WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $don = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$don || $don['payment_method'] !== 'vietqr' || $don['payment_status'] !== 'unpaid' || $don['status'] === 'cancelled') {
            throw new RuntimeException('Đơn không thể đánh dấu đã thanh toán.');
        }
        $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $ghiChu = 'Admin xác nhận đã nhận chuyển khoản VietQR';
        $stmt = $conn->prepare('INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $id, $don['status'], $ghiChu);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        header('Location: admin.php?tab=orders&msg=pay_ok');
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Danh dau thanh toan: ' . $e->getMessage());
        header('Location: admin.php?tab=orders&msg=order_invalid');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    admin_csrf_check();
    $id           = (int)($_POST['id'] ?? 0);
    $trangThaiMoi = trim((string)($_POST['new_status'] ?? ''));
    $ghiChu       = trim((string)($_POST['note'] ?? ''));
    if ($id <= 0 || mb_strlen($ghiChu) > 255) {
        header('Location: admin.php?tab=orders&msg=order_invalid');
        exit;
    }
    $ghiChu = ($ghiChu !== '') ? $ghiChu : null;
    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $don = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $cu = $don['status'] ?? '';
        if (!$don || !in_array($trangThaiMoi, $duocPhep[$cu] ?? [], true)) {
            throw new RuntimeException('Chuyển trạng thái không hợp lệ hoặc đơn không tồn tại.');
        }

        if ($trangThaiMoi === 'cancelled') {
            $ct = $conn->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? FOR UPDATE');
            $ct->bind_param('i', $id);
            $ct->execute();
            $dsCT = $ct->get_result()->fetch_all(MYSQLI_ASSOC);
            $ct->close();
            $traKho = $conn->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?');
            foreach ($dsCT as $chiTiet) {
                $maSP    = (int)$chiTiet['product_id'];
                $soLuong = (int)$chiTiet['quantity'];
                if ($maSP <= 0 || $soLuong <= 0) continue;
                $traKho->bind_param('ii', $soLuong, $maSP);
                $traKho->execute();
            }
            $traKho->close();
        }

        $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $trangThaiMoi, $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $id, $trangThaiMoi, $ghiChu);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        header('Location: admin.php?tab=orders&msg=order_updated');
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Cap nhat don hang: ' . $e->getMessage());
        header('Location: admin.php?tab=orders&msg=' . ($e instanceof RuntimeException ? 'order_invalid' : 'order_error'));
    }
    exit;
}

/* ------------------------------------------------------------------
   [ĐÁNH GIÁ] ẨN / HIỆN / XÓA đánh giá, ĐÁNH DẤU ĐÃ ĐỌC (chỉ nhận POST + CSRF)
------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_action'])) {
    admin_csrf_check();
    $rid    = (int)($_POST['id'] ?? 0);
    $hanhDong = (string)$_POST['review_action'];
    $locBack  = in_array($_POST['f'] ?? '', ['all', 'new', 'hidden'], true) ? $_POST['f'] : 'all';
    $back     = 'admin.php?tab=reviews&f=' . $locBack . '&p=' . max(1, (int)($_POST['p'] ?? 1));
    $ketQua   = 'review_error';
    try {
        if ($hanhDong === 'read_all') {
            $conn->query('UPDATE product_reviews SET is_read = 1, updated_at = updated_at WHERE is_read = 0');
            $ketQua = 'review_read';
        } elseif ($rid > 0 && ($hanhDong === 'hide' || $hanhDong === 'show')) {
            $tt = $hanhDong === 'hide' ? 'hidden' : 'visible';
            $stmt = $conn->prepare('UPDATE product_reviews SET status = ?, is_read = 1, updated_at = updated_at WHERE id = ?');
            $stmt->bind_param('si', $tt, $rid);
            $stmt->execute();
            $stmt->close();
            $ketQua = $hanhDong === 'hide' ? 'review_hidden' : 'review_shown';
        } elseif ($rid > 0 && $hanhDong === 'delete') {
            $conn->begin_transaction();
            $stmt = $conn->prepare('SELECT file_path FROM review_media WHERE review_id = ?');
            $stmt->bind_param('i', $rid);
            $stmt->execute();
            $duongDan = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'file_path');
            $stmt->close();
            $stmt = $conn->prepare('DELETE FROM product_reviews WHERE id = ?'); // review_media tự xóa theo (ON DELETE CASCADE)
            $stmt->bind_param('i', $rid);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
            foreach ($duongDan as $p) {
                $f = rv_upload_dir() . basename($p);
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            $ketQua = 'review_deleted';
        }
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $e2) {}
        error_log('admin/danhgia: ' . $e->getMessage());
    }
    header('Location: ' . $back . '&msg=' . $ketQua);
    exit;
}

$tab = 'products';
if (isset($_GET['tab']) && in_array($_GET['tab'], ['users', 'orders', 'reviews'], true)) {
    $tab = $_GET['tab'];
}

$thong_bao = [
    'deleted'       => ['ok',  'Đã xóa sản phẩm thành công!'],
    'updated'       => ['ok',  'Đã cập nhật thông tin sản phẩm thành công!'],
    'added'         => ['ok',  'Đã thêm sản phẩm mới thành công!'],
    'delete_failed' => ['err', 'Không xóa được sản phẩm (có thể đang được đơn hàng sử dụng).'],
    'invalid'       => ['err', 'Dữ liệu không hợp lệ (giá và tồn kho phải là số không âm).'],
    'order_updated' => ['ok',  'Đã cập nhật trạng thái đơn hàng.'],
    'pay_ok'        => ['ok',  'Đã đánh dấu đơn hàng là đã thanh toán.'],
    'order_invalid' => ['err', 'Chuyển trạng thái không hợp lệ hoặc đơn hàng không tồn tại.'],
    'order_error'   => ['err', 'Lỗi cơ sở dữ liệu khi cập nhật đơn hàng. Vui lòng thử lại.'],
    'review_hidden'  => ['ok',  'Đã ẩn đánh giá (khách hàng không còn thấy ở trang sản phẩm).'],
    'review_shown'   => ['ok',  'Đã hiện lại đánh giá.'],
    'review_deleted' => ['ok',  'Đã xóa đánh giá cùng ảnh/video đính kèm.'],
    'review_read'    => ['ok',  'Đã đánh dấu tất cả đánh giá là đã đọc.'],
    'review_error'   => ['err', 'Không thực hiện được. Kiểm tra đã chạy file danhgia.sql chưa.'],
];
$msg = $_GET['msg'] ?? '';

/* ------------------------------------------------------------------
   [ĐÁNH GIÁ] Dữ liệu tab "Đánh giá sản phẩm" + số đánh giá chưa đọc (hiện ở menu)
------------------------------------------------------------------- */
$rvChuaDoc = 0;
$rvLoiBang = false;
$rvLoc = in_array($_GET['f'] ?? '', ['new', 'hidden'], true) ? $_GET['f'] : 'all';
$rvTrang = max(1, (int)($_GET['p'] ?? 1));
$rvMoiTrang = 15;
$rvDs = []; $rvMedia = []; $rvTong = 0; $rvSoTrang = 1;
$rvDem = ['all' => 0, 'new' => 0, 'hidden' => 0];
try {
    $dem = $conn->query("SELECT COUNT(*) AS a, COALESCE(SUM(is_read = 0), 0) AS n, COALESCE(SUM(status = 'hidden'), 0) AS hd FROM product_reviews")->fetch_assoc();
    $rvDem = ['all' => (int)$dem['a'], 'new' => (int)$dem['n'], 'hidden' => (int)$dem['hd']];
    if ($tab === 'reviews') {
        $where = $rvLoc === 'new' ? 'WHERE r.is_read = 0' : ($rvLoc === 'hidden' ? "WHERE r.status = 'hidden'" : '');
        $rvTong = $rvDem[$rvLoc];
        $rvSoTrang = max(1, (int)ceil($rvTong / $rvMoiTrang));
        $rvTrang = min($rvTrang, $rvSoTrang);
        $off = ($rvTrang - 1) * $rvMoiTrang;
        $stmt = $conn->prepare(
            "SELECT r.id, r.product_id, r.order_id, r.rating, r.comment, r.status, r.is_read, r.created_at, r.updated_at,
                    p.product_name, u.username, u.full_name, u.email
               FROM product_reviews r
               JOIN products p ON p.id = r.product_id
               JOIN users u ON u.id = r.user_id
               $where
              ORDER BY r.is_read ASC, r.updated_at DESC, r.id DESC
              LIMIT ? OFFSET ?"
        );
        $stmt->bind_param('ii', $rvMoiTrang, $off);
        $stmt->execute();
        $rvDs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if ($rvDs) {
            $ids = implode(',', array_map(fn($r) => (int)$r['id'], $rvDs));
            foreach ($conn->query("SELECT review_id, file_path, media_type FROM review_media WHERE review_id IN ($ids) ORDER BY id")->fetch_all(MYSQLI_ASSOC) as $m) {
                $rvMedia[(int)$m['review_id']][] = $m;
            }
            // Mở tab = đã đọc những đánh giá đang hiển thị (vẫn giữ nhãn "Mới" ở lần hiển thị này)
            $conn->query("UPDATE product_reviews SET is_read = 1, updated_at = updated_at WHERE is_read = 0 AND id IN ($ids)");
        }
    }
    $rvChuaDoc = (int)$conn->query('SELECT COUNT(*) AS n FROM product_reviews WHERE is_read = 0')->fetch_assoc()['n'];
} catch (Throwable $e) {
    $rvLoiBang = true;
    error_log('admin/danhgia: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang Quản Trị - Kaido Shop</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="image/kaidoLogomini.png">
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

    <!-- 2. BÊN PHẢI: KHU VỰC HIỂN THỊ THÔNG TIN CHÍNH -->
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

        <!-- Khung Nội Dung Chính -->
        <div class="content-body">
            <div class="card-box">
                
                <?php if (isset($thong_bao[$msg])): ?>
                    <div class="<?= $thong_bao[$msg][0] === 'ok' ? 'alert-success' : 'alert-error' ?>">
                        <?= h($thong_bao[$msg][1]) ?>
                    </div>
                <?php endif; ?>

                <?php if ($tab === 'products'): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                        <h2 style="margin:0; font-size:20px; color:#f8fafc;">Danh sách sản phẩm hiện có</h2>
                        <a href="themsanpham.php" class="btn-action btn-save" style="padding:10px 18px; text-decoration:none;">+ Thêm sản phẩm mới</a>
                    </div>
                    
                    <div style="overflow-x:auto;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên sản phẩm</th>
                                    <th>SKU</th>
                                    <th>Giá (VNĐ)</th>
                                    <th>Tồn kho</th>
                                    <th>Trạng thái</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $result = $conn->query('SELECT id, product_name, sku, price, stock_qty, status FROM products ORDER BY id DESC');
                            if ($result->num_rows > 0):
                                while ($row = $result->fetch_assoc()):
                                    $fid = 'f' . (int)$row['id'];
                            ?>
                                <tr>
                                    <td><?= (int)$row['id'] ?></td>
                                    <td><strong><?= h($row['product_name']) ?></strong></td>
                                    <td><code style="color:#38bdf8;"><?= h($row['sku']) ?></code></td>
                                    <td class="form-edit">
                                        <input form="<?= $fid ?>" type="number" min="0" step="1" name="price" value="<?= h($row['price']) ?>">
                                    </td>
                                    <td class="form-edit">
                                        <input form="<?= $fid ?>" type="number" min="0" step="1" name="stock_qty" value="<?= (int)$row['stock_qty'] ?>">
                                    </td>
                                    <td>
                                        <span style="padding: 3px 8px; border-radius: 4px; font-size:12px; background: <?= $row['status'] === 'active' ? 'rgba(16,185,129,0.2)' : 'rgba(239,68,68,0.2)' ?>; color: <?= $row['status'] === 'active' ? '#34d399' : '#fca5a5' ?>;">
                                            <?= h($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <!-- Form lưu chỉnh sửa nhanh -->
                                        <form id="<?= $fid ?>" class="inline-form" action="admin.php?tab=products" method="POST">
                                            <?= admin_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                            <button type="submit" name="update_product" class="btn-action btn-save">Lưu</button>
                                        </form>

                                        <a href="suasanpham.php?id=<?= (int)$row['id'] ?>" class="btn-action" style="background:#3b82f6;">Sửa đầy đủ</a>
                                        <a href="thongso.php?product_id=<?= (int)$row['id'] ?>" class="btn-action" style="background:#8b5cf6;">Thông số</a>
                                        
                                        <?php if ((int)$row['stock_qty'] <= 5): ?>
                                            <span style="display:inline-block; padding:4px 8px; font-size:12px; border-radius:4px; font-weight:bold; background:<?= (int)$row['stock_qty'] === 0 ? 'rgba(239, 68, 68, 0.2)' : 'rgba(245, 158, 11, 0.2)' ?>; color:<?= (int)$row['stock_qty'] === 0 ? '#f87171' : '#fbbf24' ?>;">
                                                <?= (int)$row['stock_qty'] === 0 ? 'Hết hàng' : 'Sắp hết' ?>
                                            </span>
                                        <?php endif; ?>

                                        <!-- Xóa bằng POST + CSRF -->
                                        <form class="inline-form" action="admin.php?tab=products" method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này không?');">
                                            <?= admin_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                            <button type="submit" name="delete_product" class="btn-action btn-delete">Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php
                                endwhile;
                            else:
                                echo "<tr><td colspan='7' style='text-align:center; color:#94a3b8;'>Chưa có sản phẩm nào trong cơ sở dữ liệu.</td></tr>";
                            endif;
                            ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($tab === 'orders'): ?>
                    <h2 style="margin-top:0; font-size:20px; color:#f8fafc; margin-bottom: 20px;">Danh sách đơn hàng</h2>
                    <?php
                    $dsDon = $conn->query(
                        'SELECT id, recipient_name, phone, shipping_address, created_at, total_amount, status, payment_method, payment_status
                         FROM orders ORDER BY id DESC LIMIT 200'
                    )->fetch_all(MYSQLI_ASSOC);

                    $spTheoDon = [];
                    if ($dsDon) {
                        $cuoi  = end($dsDon);
                        $minId = (int)$cuoi['id'];
                        $stmtSP = $conn->prepare('SELECT order_id, product_name, quantity FROM order_items WHERE order_id >= ? ORDER BY order_id, id');
                        $stmtSP->bind_param('i', $minId);
                        $stmtSP->execute();
                        foreach ($stmtSP->get_result()->fetch_all(MYSQLI_ASSOC) as $it) {
                            $spTheoDon[(int)$it['order_id']][] = $it;
                        }
                        $stmtSP->close();
                    }
                    ?>
                    <div style="overflow-x:auto;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Người nhận</th>
                                    <th>Sản phẩm</th>
                                    <th>Tổng tiền</th>
                                    <th>Thanh toán</th>
                                    <th>Trạng thái</th>
                                    <th>Cập nhật</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$dsDon): ?>
                                <tr><td colspan="7" style="text-align:center; color:#94a3b8;">Chưa có đơn hàng nào.</td></tr>
                            <?php else: ?>
                                <?php foreach ($dsDon as $d):
                                    $tuyChon = $duocPhep[$d['status']] ?? [];
                                    $dsSP    = $spTheoDon[(int)$d['id']] ?? [];
                                ?>
                                <tr>
                                    <td>
                                        <strong style="color:#38bdf8;"><?= h(maDon((int)$d['id'])) ?></strong><br>
                                        <span class="order-sub"><?= h(date('d/m/Y H:i', strtotime($d['created_at']))) ?></span>
                                    </td>
                                    <td>
                                        <strong><?= h($d['recipient_name']) ?></strong><br>
                                        <span class="order-sub">SĐT: <?= h($d['phone']) ?></span><br>
                                        <span class="order-sub">Địa chỉ: <?= h($d['shipping_address']) ?></span>
                                    </td>
                                    <td>
                                        <?php foreach ($dsSP as $it): ?>
                                            <div style="font-size:13px; margin-bottom:2px;">• <?= h($it['product_name']) ?> <strong style="color:#ff416c;">× <?= (int)$it['quantity'] ?></strong></div>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><strong style="color:#34d399;"><?= h(tien($d['total_amount'])) ?></strong></td>
                                    <td>
                                        <?php if ($d['payment_method'] === 'vietqr'): ?>
                                            <span class="order-sub">VietQR</span><br>
                                            <?php if ($d['payment_status'] === 'paid'): ?>
                                                <strong style="color:#34d399;">✔ Đã thanh toán</strong>
                                                <?php if ($d['status'] === 'cancelled'): ?><br><span style="color:#f87171;font-size:12px;">Đơn đã hủy – cần hoàn tiền tay</span><?php endif; ?>
                                            <?php elseif ($d['status'] === 'cancelled'): ?>
                                                <span class="order-sub">Đã hủy</span>
                                            <?php else: ?>
                                                <span style="color:#fbbf24;">Chưa thanh toán</span>
                                                <form action="admin.php?tab=orders" method="POST" style="margin-top:4px;" onsubmit="return confirm('Xác nhận đã nhận đủ tiền chuyển khoản?');">
                                                    <?= admin_csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                                    <button type="submit" name="mark_paid" class="btn-action btn-save">Đã nhận tiền</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="order-sub">COD</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="order-badge" style="background:<?= h(mauTrangThai($d['status'])) ?>;">
                                            <?= h(tenTrangThai($d['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($tuyChon): ?>
                                        <form class="order-form" action="admin.php?tab=orders" method="POST">
                                            <?= admin_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                            <select name="new_status" aria-label="Chọn trạng thái mới">
                                                <?php foreach ($tuyChon as $ma): ?>
                                                    <option value="<?= h($ma) ?>"><?= h(tenTrangThai($ma)) ?></option>
                                                <?php endforeach; ?>
                                            </select><br>
                                            <input type="text" name="note" maxlength="255" placeholder="Ghi chú (nếu có)">
                                            <button type="submit" name="update_order" class="btn-action btn-save">Cập nhật</button>
                                        </form>
                                        <?php else: ?>
                                            <span class="order-sub">Đã hoàn tất / Hoàn tác</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($tab === 'reviews'): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                        <h2 style="margin:0; font-size:20px;">Đánh giá sản phẩm từ khách hàng</h2>
                        <?php if ($rvDem['new'] > 0): ?>
                        <form method="POST" action="admin.php?tab=reviews" class="inline-form">
                            <?= admin_csrf_field() ?>
                            <input type="hidden" name="f" value="<?= h($rvLoc) ?>">
                            <button type="submit" name="review_action" value="read_all" class="btn-action" style="background:#475569;">Đánh dấu tất cả đã đọc</button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <?php if ($rvLoiBang): ?>
                        <div class="alert-error">Chưa có bảng đánh giá trong CSDL. Hãy chạy file <code>danhgia.sql</code> trong phpMyAdmin.</div>
                    <?php else: ?>
                    <div class="rv-filter" style="margin-bottom:14px;">
                        <a class="<?= $rvLoc === 'all' ? 'on' : '' ?>" href="admin.php?tab=reviews&amp;f=all">Tất cả (<?= (int)$rvDem['all'] ?>)</a>
                        <a class="<?= $rvLoc === 'new' ? 'on' : '' ?>" href="admin.php?tab=reviews&amp;f=new">Chưa đọc (<?= (int)$rvDem['new'] ?>)</a>
                        <a class="<?= $rvLoc === 'hidden' ? 'on' : '' ?>" href="admin.php?tab=reviews&amp;f=hidden">Đang ẩn (<?= (int)$rvDem['hidden'] ?>)</a>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="admin-table">
                            <thead>
                                <tr><th>Sản phẩm</th><th>Khách hàng</th><th>Đánh giá</th><th>Thời gian</th><th>Hành động</th></tr>
                            </thead>
                            <tbody>
                            <?php if (!$rvDs): ?>
                                <tr><td colspan="5" style="text-align:center; color:#94a3b8;">Không có đánh giá nào.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($rvDs as $r): $moi = (int)$r['is_read'] === 0; $an = $r['status'] === 'hidden'; ?>
                                <tr class="<?= $moi ? 'rv-row-new' : '' ?>">
                                    <td>
                                        <a href="../sanpham/chitietsanpham.php?id=<?= (int)$r['product_id'] ?>#danhgia" target="_blank" style="color:#38bdf8; font-weight:600;"><?= h($r['product_name']) ?></a><br>
                                        <span class="order-sub">Đơn <?= h(maDon((int)$r['order_id'])) ?></span>
                                    </td>
                                    <td>
                                        <strong><?= h($r['username']) ?></strong><?= $moi ? '<span class="rv-badge-new">MỚI</span>' : '' ?><br>
                                        <span class="order-sub"><?= h($r['full_name']) ?></span><br>
                                        <span class="order-sub"><?= h($r['email']) ?></span>
                                    </td>
                                    <td style="min-width:260px; max-width:420px;">
                                        <div style="color:#f59e0b; letter-spacing:2px; font-size:16px;"><?= h(rv_stars((int)$r['rating'])) ?></div>
                                        <?php if (trim((string)$r['comment']) !== ''): ?>
                                            <div style="margin-top:4px; white-space:pre-wrap; word-break:break-word;"><?= h($r['comment']) ?></div>
                                        <?php else: ?>
                                            <div class="order-sub">(không có nhận xét)</div>
                                        <?php endif; ?>
                                        <?php if (!empty($rvMedia[(int)$r['id']])): ?>
                                            <div class="rv-thumbs">
                                            <?php foreach ($rvMedia[(int)$r['id']] as $m): ?>
                                                <?php if ($m['media_type'] === 'video'): ?>
                                                    <video src="<?= h(rv_media_url($m['file_path'])) ?>" controls preload="metadata"></video>
                                                <?php else: ?>
                                                    <a href="<?= h(rv_media_url($m['file_path'])) ?>" target="_blank" rel="noopener"><img src="<?= h(rv_media_url($m['file_path'])) ?>" alt="" loading="lazy"></a>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($an): ?><div style="margin-top:6px;"><span class="order-badge" style="background:#64748b;">Đang ẩn</span></div><?php endif; ?>
                                    </td>
                                    <td><span class="order-sub"><?= h(date('d/m/Y H:i', strtotime($r['created_at']))) ?></span>
                                        <?php if ($r['updated_at'] !== $r['created_at']): ?><br><span class="order-sub">sửa: <?= h(date('d/m/Y H:i', strtotime($r['updated_at']))) ?></span><?php endif; ?></td>
                                    <td>
                                        <form class="inline-form" method="POST" action="admin.php?tab=reviews">
                                            <?= admin_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <input type="hidden" name="f" value="<?= h($rvLoc) ?>">
                                            <input type="hidden" name="p" value="<?= (int)$rvTrang ?>">
                                            <button type="submit" name="review_action" value="<?= $an ? 'show' : 'hide' ?>" class="btn-action" style="background:<?= $an ? '#10b981' : '#f59e0b' ?>;"><?= $an ? 'Hiện lại' : 'Ẩn' ?></button>
                                        </form>
                                        <form class="inline-form" method="POST" action="admin.php?tab=reviews" onsubmit="return confirm('Xóa vĩnh viễn đánh giá này cùng ảnh/video đính kèm?');">
                                            <?= admin_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <input type="hidden" name="f" value="<?= h($rvLoc) ?>">
                                            <input type="hidden" name="p" value="<?= (int)$rvTrang ?>">
                                            <button type="submit" name="review_action" value="delete" class="btn-action btn-delete">Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($rvSoTrang > 1): ?>
                        <div class="rv-filter" style="margin-top:14px;">
                            <?php for ($i = 1; $i <= $rvSoTrang; $i++): ?>
                                <a class="<?= $i === $rvTrang ? 'on' : '' ?>" href="admin.php?tab=reviews&amp;f=<?= h($rvLoc) ?>&amp;p=<?= $i ?>"><?= $i ?></a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                    <?php endif; ?>

                <?php else: ?>
                    <?php
                    $customerCount = (int)$conn->query("SELECT COUNT(*) AS n FROM users WHERE role = 'user'")->fetch_assoc()['n'];
                    ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h2 style="margin:0; font-size:20px; color:#f8fafc;">Quản lý khách hàng</h2>
                        <span style="color:#94a3b8;">Tổng khách hàng: <strong style="color:#38bdf8;"><?= $customerCount ?></strong></span>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên đăng nhập</th>
                                    <th>Email</th>
                                    <th>Họ và tên</th>
                                    <th>Số điện thoại</th>
                                    <th>Vai trò</th>
                                    <th>Ngày tạo</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $result = $conn->query("SELECT id, username, email, full_name, phone, role, created_at FROM users WHERE role = 'user' ORDER BY id DESC");
                            if ($result->num_rows > 0):
                                while ($row = $result->fetch_assoc()):
                            ?>
                                <tr>
                                    <td><?= (int)$row['id'] ?></td>
                                    <td><strong><?= h($row['username']) ?></strong></td>
                                    <td><?= h($row['email']) ?></td>
                                    <td><?= h($row['full_name']) ?></td>
                                    <td><?= h($row['phone']) ?></td>
                                    <td>
                                        <span style="padding:4px 10px; border-radius:12px; font-size:12px; font-weight:bold; background:<?= $row['role'] === 'admin' ? 'linear-gradient(90deg, #ff416c, #ff4b2b)' : '#334155' ?>; color:white;">
                                            <?= h($row['role']) ?>
                                        </span>
                                    </td>
                                    <td><span class="order-sub"><?= h($row['created_at']) ?></span></td>
                                </tr>
                            <?php
                                endwhile;
                            else:
                                echo "<tr><td colspan='7' style='text-align:center; color:#94a3b8;'>Chưa có tài khoản người dùng nào.</td></tr>";
                            endif;
                            ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>
</body>
<script src="../js/slide.js"></script>
</html>
