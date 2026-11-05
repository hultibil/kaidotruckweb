<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../donhang/trangthai.php';
$userId = dhRequireLogin();

$st = $conn->prepare("SELECT p.id, p.product_name, p.description, p.price, p.stock_qty, p.status, p.image_path FROM san_pham_yeu_thich y JOIN products p ON p.id=y.product_id WHERE y.user_id=? ORDER BY y.created_at DESC");
$st->bind_param('i', $userId);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../image/kaidoLogomini.png">
    <title>Sản phẩm yêu thích - Kaido Shop</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* CSS tuỳ chỉnh đồng bộ giao diện Kaido Shop cho trang Yêu Thích */
        .fav-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 15px;
        }
        .fav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }
        .fav-card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.3s, background 0.3s;
        }
        .fav-card:hover {
            background: rgba(0, 0, 0, 0.5);
            transform: translateY(-4px);
        }
        .fav-img-box {
            width: 100%;
            height: 180px;
            background: #111;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }
        .fav-img-box img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover;
        }
        .no-img {
            color: #888;
            font-size: 13px;
            text-align: center;
        }
        .fav-card h2 {
            font-size: 18px;
            margin: 8px 0;
            color: #fff;
        }
        .fav-card p {
            font-size: 14px;
            color: #cbd5e1;
            margin: 5px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .fav-price {
            font-size: 16px;
            font-weight: bold;
            color: #ff4b2b;
            margin: 10px 0;
        }
        .fav-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 15px;
        }
        .fav-actions button, .fav-actions a.btn-action {
            width: 100%;
            padding: 9px 12px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .fav-actions button:hover, .fav-actions a.btn-action:hover {
            opacity: 0.85;
        }
        .btn-remove {
            background: #ef4444;
            color: white;
        }
        .btn-cart {
            background: linear-gradient(to right, #ff416c, #ff4b2b);
            color: white;
        }
        .btn-compare {
            background: #3b82f6;
            color: white;
        }
        .btn-detail {
            color: #38bdf8;
            text-decoration: none;
            font-size: 13px;
            display: inline-block;
            margin-top: 5px;
        }
        .btn-detail:hover {
            text-decoration: underline;
        }
        .empty-box {
            text-align: center;
            background: rgba(0,0,0,0.2);
            padding: 40px;
            border-radius: 15px;
            margin-top: 30px;
        }
        .empty-box a {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background: linear-gradient(to right, #ff416c, #ff4b2b);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>

<!-- HEADER MENU CHUNG -->
<div class="header">
    <img class="logo" src="../image/kaidologo.jpg" alt="Logo">
    <h2>Sản phẩm yêu thích</h2>
    <div class="nut0"><a href="../index.php">Trang chủ</a></div>
    <div class="nut1"><a href="../khac/khac.html">Dự án khác</a></div>
    <div class="nut2"><a href="../hoithem.html">Trò chuyện</a></div>
    <div class="nut4chon"><a href="../tienich.php">Tài khoản/Mua hàng</a></div>
    <div class="nut5"><a href="../kythuat/details.html">Chi tiết kỹ thuật</a></div>
    <div class="nut6"><a href="../Blog/blog.html">Tin tức</a></div>
    <div class="nut3"><a href="../gamemini.html">Game mini</a></div>
</div>

<div class="fav-container">
    <h1>♥ DANH SÁCH SẢN PHẨM YÊU THÍCH</h1>
    <?php if (!$rows): ?>
        <div class="empty-box">
            <p style="font-size: 18px; color: #aaa;">Bạn chưa lưu sản phẩm yêu thích nào.</p>
            <a href="shop.php">Khám phá cửa hàng ngay</a>
        </div>
    <?php else: ?>
        <div class="fav-grid">
        <?php foreach ($rows as $sp): ?>
            <article class="fav-card">
                <div>
                    <div class="fav-img-box">
                        <?php $img = trim((string)($sp['image_path'] ?? '')); ?>
                        <?php if ($img !== '' && is_file(__DIR__ . '/../image/uploads/' . basename($img))): ?>
                            <img src="../image/uploads/<?= h(basename($img)) ?>" alt="<?= h($sp['product_name']) ?>">
                        <?php else: ?>
                            <div class="no-img">Chưa có ảnh minh họa</div>
                        <?php endif; ?>
                    </div>
                    <h2><?= h($sp['product_name']) ?></h2>
                    <p><?= h($sp['description']) ?></p>
                    <div class="fav-price"><?= h(tien($sp['price'])) ?></div>
                    <a class="btn-detail" href="chitietsanpham.php?id=<?= (int)$sp['id'] ?>">Xem chi tiết sản phẩm →</a>
                </div>

                <div class="fav-actions">
                    <?php if ($sp['status'] === 'active' && (int)$sp['stock_qty'] > 0): ?>
                        <form method="post" action="../Giohang/them_gio.php">
                            <input type="hidden" name="csrf_token" value="<?= h(dhToken()) ?>">
                            <input type="hidden" name="product_id" value="<?= (int)$sp['id'] ?>">
                            <input type="hidden" name="so_luong" value="1">
                            <button type="submit" class="btn-cart">🛒 Thêm vào giỏ</button>
                        </form>
                    <?php endif; ?>

                    <a class="btn-action btn-compare" href="tuongthich.php?a=<?= (int)$sp['id'] ?>">So tương thích</a>

                    <form action="xuly_yeuthich.php" method="post" onsubmit="return confirm('Bạn có chắc muốn bỏ sản phẩm này khỏi danh sách yêu thích?');">
                        <input type="hidden" name="csrf_token" value="<?= h(dhToken()) ?>">
                        <input type="hidden" name="product_id" value="<?= (int)$sp['id'] ?>">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="return_page" value="favorites">
                        <button type="submit" class="btn-action btn-remove">Bỏ yêu thích</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="quaylai">
    <a href="shop.php">← Quay lại cửa hàng</a>
</div>

<div id="info">
    <div class="thongtinweb">
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


<!--Đặt Cho loading-->
<script>
window.addEventListener("load", function() {
    const loading = document.getElementById("loading");
    loading.style.opacity = "0";
    setTimeout(function() {
        loading.style.display = "none";
    }, 500);
});
</script>
</body>
</html>