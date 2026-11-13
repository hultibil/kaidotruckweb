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
        /* --- LIGHT MODE STYLING --- */
        body.light-mode {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
        }
        body.light-mode .history-container h1 {
            color: #0f172a !important;
        }
        body.light-mode .muted-text {
            color: #64748b !important;
        }
        body.light-mode .history-panel,
        body.light-mode .empty-history {
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05) !important;
        }
        body.light-mode .empty-history p {
            color: #475569 !important;
        }
        body.light-mode .history-table th {
            background: #f8fafc !important;
            color: #475569 !important;
            border-bottom: 2px solid #e2e8f0 !important;
        }
        body.light-mode .history-table td {
            border-bottom: 1px solid #e2e8f0 !important;
            color: #1e293b !important;
        }
        body.light-mode .history-table tr:hover {
            background: #f8fafc !important;
        }
        body.light-mode .order-code {
            color: #0284c7 !important; /* Xanh đậm dễ đọc trên nền trắng */
        }
        body.light-mode .order-price {
            color: #0ad897 !important; /* Xanh lá đậm dễ đọc trên nền trắng */
        }
        body.light-mode .theme-toggle-btn {
            background: rgba(0, 0, 0, 0.08) !important;
            border: 1px solid rgba(0, 0, 0, 0.2) !important;
            color: #0f172a !important;
        }
    </style>
</head>
<body>

<<!-- THANH MÀU XÁM (Đầu trang - Smart Header: Tự ẩn khi lướt xuống, hiện khi lướt lên) -->
    <div class="header" id="mainHeader">
        <div class="header-left">
            <img class="logo" src="../image/kaidologo.jpg">
            <h2>Trang chủ giới thiệu Kaido</h2>
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
    
    <!-- THANH ĐIỀU HƯỚNG MỚI (Nằm dưới thanh màu xám, không ghim, lướt ngang trên mobile) -->
    <div class="nav-container">
        <div class="nav-scroll-wrapper">
            <div class="nut0">
                <a href="../index.php" onclick="clickSound()">Trang chủ</a>
            </div>
            <div class="nut1">
                <a>Dự án khác</a>
            </div>
            <div class="nut2">
                <a href="../hoithem.php">Trò chuyện</a>
            </div>
            <div class="nut4chon">
                <a href="../tienich.php" onclick="clickSound()">Tài khoản/Mua hàng</a>
            </div>
            <div class="nut5">
                <a href="../kythuat/details.php">Chi tiết kỹ thuật</a>
            </div>
            <div class="nut6">
                <a href="../Blog/blog.php" onclick="clickSound()">Tin tức</a>
            </div>
            <div class="nut3">
                <a href="../gamemini.php" onclick="clickSound()">Game mini</a>
            </div>
        </div>
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

   <div class="thongtinweb">
    <h4 class="text">Bạn đang ở cuối trang.</h4>
<div class="footer">
    <div>
    <h3 class="text">Social Media</h3>
    <p>--Mạng xã hội Tik Tok--</p>
    <div class="tiktok"><a href="https://www.tiktok.com/@elfaria425">Tik tok: @elfaria425 | Chính chủ chế tạo ngoại hình, thiết kế xe chính</a></div><br>
    <div class="tiktok2"><a href=https://www.tiktok.com/@meihirou>Tik tok: @meihirou | Phụ trợ, kỹ sư, thiết kế trang web</a></div><br>
    <div class="tiktok3"><a href="https://www.tiktok.com/@hoai.anh2017.3">Tik tok: @hoai.anh2017.3 | Phụ trợ, thiết kế trang web</a><br><p>Ủng hộ chúng mình bằng cách nhấn Follow <3 </p></div>
    <p>--Liên hệ bằng email--</p>
    <p>akahiru5@gmail.com</p>
    <p>--Kênh Youtube--</p>
    <div class="youtube">
    <a href="https://www.youtube.com/@Nguy%E1%BB%85nqu%E1%BB%91cc%C6%B0%E1%BB%9Dng-w9j4l">Kênh Youtube: Cường Nguyễn</a></div><br>
    <div class="youtube2">
    <a href="https://www.youtube.com/@tienlam2159">Kênh Youtube: Lâm Tiến</a>
    </div>
    <p>Ủng hộ chúng mình bằng cách nhấn Subcribe <3 </p>
</div>
    <div>
        <h3 class="text">Thông tin trang web</h3>
        <p>Xây dựng thương hiệu tại: Thành phố Gia Lai </p>
        <p>Được thiết kế bởi: quốc cường rc</p>
        <p>Bản quyền © 2025 Kaido. All rights reserved.</p>
    </div>
    <h1 id="gio"></h1>
    <script>
function capNhatGio() {
    let now = new Date();
    let gio = now.toLocaleTimeString();
    document.getElementById("gio").innerHTML = gio;
}
setInterval(capNhatGio, 1000);
</script>
    <p class="date">Cập nhật trang web lần cuối: 29/09/2026</p>
</div>

</body>
<script src="../js/slide.js"></script>
</html>