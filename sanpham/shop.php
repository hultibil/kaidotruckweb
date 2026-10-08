<?php
// 1. Nhúng file kết nối cơ sở dữ liệu
require_once '../db.php'; // Thay đổi đường dẫn tới db.php cho đúng cấu trúc thư mục của bạn

// 2. Truy vấn lấy tất cả sản phẩm từ Database
$sql = "SELECT * FROM products ORDER BY id DESC";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../image/kaidoLogomini.png">
    <title>Kaido Shop</title>
    <link rel="stylesheet" href="../css/shop.css">
</head>
<body>

<!-- TRANG LOADING -->
<div id="loading">
    <img src="../image/imgload.png">
    <p>Đang tải, gần xong rồi!!<span id="dots">...</span></p>
</div>

<div class="header">
    <img class="logo" src="../image/kaidologo.jpg">
    <h2>Cửa hàng linh kiện Kaido</h2>
    <div class="nut0"><a href="../index.php" onclick="clickSound()">Trang chủ</a></div>
    <div class="nut1"><a href="../khac/khac.html" onclick="clickSound()">Dự án khác</a></div>
    <div class="nut2"><a href="../hoithem.html" onclick="clickSound()">Trò chuyện</a></div>
    <div class="nut4chon"><a href="../tienich.html" onclick="clickSound()">Tài khoản/Mua hàng</a></div>
    <div class="nut5"><a href="../kythuat/details.html">Chi tiết kỹ thuật</a></div>
    <div class="nut6"><a href="../Blog/blog.html" onclick="clickSound()">Tin tức</a></div>
    <div class="nut3"><a href="../gamemini.html" onclick="clickSound()">Game mini</a></div>
</div>

<h1>Kaido Shop</h1>

<!-- KAIDO PROMOTION CAROUSEL -->
<div class="kaido-carousel">
    <div class="kaido-slides">
        <div class="kaido-slide active">
            <img src="../image/xev5trinhchieu.jpg" alt="KAIDO">
            <div class="kaido-banner-content">
                <span>KAIDO RC</span>
                <h2>XE RC V5 — PHIÊN BẢN MỚI</h2>
                <p>Khám phá những cải tiến mới nhất của KAIDO.</p>
                <a href="../index.html">XEM NGAY</a>
            </div>
        </div>
        <div class="kaido-slide">
            <img src="../image/loatrinhchieu.jpg" alt="KAIDO DIY">
            <div class="kaido-banner-content">
                <span>KAIDO DIY</span>
                <h2>DIY — TẬN DỤNG & SÁNG TẠO</h2>
                <p>Biến linh kiện cũ thành những sản phẩm hữu ích.</p>
                <a href="../khac/khac.html">KHÁM PHÁ</a>
            </div>
        </div>
        <div class="kaido-slide">
            <img src="../image/chiptrinhchieu.jpg" alt="KAIDO RC">
            <div class="kaido-banner-content">
                <span>DIY COMPONENTS</span>
                <h2>LINH KIỆN BAO LA, GIÁ DỄ TIẾP CẬN, BẢO HÀNH DÀI HẠN</h2>
                <p>Xây dựng với dự án DIY của riêng bạn.</p>
                <a href="#dautrang">MUA SẮM</a>
            </div>
        </div>
    </div>
    <button class="kaido-prev" onclick="kaidoChangeSlide(-1)">❮</button>
    <button class="kaido-next" onclick="kaidoChangeSlide(1)">❯</button>
    <div class="kaido-dots">
        <span class="kaido-dot active" onclick="kaidoCurrentSlide(0)"></span>
        <span class="kaido-dot" onclick="kaidoCurrentSlide(1)"></span>
        <span class="kaido-dot" onclick="kaidoCurrentSlide(2)"></span>
    </div>
</div>

<div id="dautrang">
<h3 class="text">Danh sách sản phẩm</h3>
<div class="shop-list">

    <?php 
    // 3. Vòng lặp lấy dữ liệu từ MySQL và sinh thẻ sản phẩm tự động
    if ($result && mysqli_num_rows($result) > 0): 
        while ($row = mysqli_fetch_assoc($result)): 
            // Xử lý hiển thị trạng thái
            $status_text = "Còn hàng";
            if ($row['status'] == 'out_of_stock' || $row['stock'] <= 0) {
                $status_text = "Đã hết hàng";
            } elseif ($row['status'] == 'hidden') {
                continue; // Bỏ qua không hiển thị nếu bị ẩn
            }
    ?>

        <article class="product-card">
            <div class="product-image">
                <!-- Hiển thị ảnh sản phẩm từ thư mục image -->
                <img src="../image/<?php echo htmlspecialchars($row['image']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
            </div>
            
            <h2><?php echo htmlspecialchars($row['name']); ?></h2>

            <p><?php echo htmlspecialchars($row['description']); ?></p>

            <p>
                Giá: <strong><?php echo number_format($row['price'], 0, ',', '.'); ?> VNĐ</strong>
            </p>

            <p>
                Tình trạng: <?php echo $status_text; ?>
            </p>

            <!-- Chuyển hướng kèm ID sản phẩm để xem chi tiết -->
            <a href="details.php?id=<?php echo $row['id']; ?>">
                Xem chi tiết
            </a>
        </article>

    <?php 
        endwhile; 
    else: 
    ?>
        <p>Hiện chưa có sản phẩm nào trong cửa hàng.</p>
    <?php endif; ?>
</div>

<div class="thongtinweb">
    <h4 class="text">Bạn đang ở cuối trang.</h4>
    <div class="footer">
        <div>
            <h3 class="text">Social Media</h3>
            <p>--Mạng xã hội Tik Tok--</p>
            <div class="tiktok"><a href="https://www.tiktok.com/@elfaria425">Tik tok: @elfaria425 | Chính chủ chế tạo ngoại hình, thiết kế xe chính</a></div><br>
            <div class="tiktok2"><a href="https://www.tiktok.com/@meihirou">Tik tok: @meihirou | Phụ trợ, kỹ sư, thiết kế trang web</a></div><br>
            <div class="tiktok3"><a href="https://www.tiktok.com/@hoai.anh2017.3">Tik tok: @hoai.anh2017.3 | Phụ trợ, thiết kế trang web</a><br><p>Ủng hộ chúng mình bằng cách nhấn Follow <3 </p></div>
            <p>--Liên hệ bằng email--</p>
            <p>akahiru5@gmail.com</p>
            <p>--Kênh Youtube--</p>
            <div class="youtube"><a href="https://www.youtube.com/@Nguy%E1%BB%85nqu%E1%BB%91cc%C6%B0%E1%BB%9Dng-w9j4l">Kênh Youtube: Cường Nguyễn</a></div><br>
            <div class="youtube2"><a href="https://www.youtube.com/@tienlam2159">Kênh Youtube: Lâm Tiến</a></div>
            <p>Ủng hộ chúng mình bằng cách nhấn Subcribe <3 </p>
        </div>
        <div>
            <h3 class="text">Thông tin trang web</h3>
            <p>Xây dựng thương hiệu tại: Thành phố Gia Lai </p>
            <p>Được thiết kế bởi: quốc cường rc</p>
            <p>Bản quyền © 2025 Kaido. All rights reserved.</p>
        </div>
        <h2 id="gio"></h2>
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

    <div class="quaylai">
        <a href="giohang.html">Giỏ hàng của bạn</a>
    </div>

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
<script src="../js/slide.js"></script>
</html>