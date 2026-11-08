<?php
session_start();
// Kiểm tra trạng thái đăng nhập sẵn ở PHP để truyền vào JavaScript
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài khoản và Mua hàng</title>
    <link rel="icon" href="image/kaidoLogomini.png">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* ==========================================================
           HIỆU ỨNG THÔNG BÁO ĐẨY GÓC TRÊN BÊN PHẢI (CHƯA ĐĂNG NHẬP)
           ========================================================== */
        .kaido-top-notification {
            position: fixed;
            top: -100px;
            right: 25px;
            background: #1f2937;
            color: white;
            padding: 15px 22px;
            border: 1px solid #374151;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 99999;
            opacity: 0;
            transition: top 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55), opacity 0.5s ease;
        }

        .kaido-top-notification.show {
            top: 25px; 
            opacity: 1;
        }

        .kaido-top-notification p {
            margin: 0;
            font-size: 14px;
            color: #d1d5db;
        }

        .kaido-top-notification a.login-quick-btn {
            background: linear-gradient(to right, #ff416c, #ff4b2b);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            white-space: nowrap;
            transition: opacity 0.2s;
        }
        .kaido-top-notification a.login-quick-btn:hover {
            opacity: 0.85;
        }

        /* Tương thích điện thoại: Hiện ở trên cùng như thông báo trạng thái */
        @media (max-width: 768px) {
            .kaido-top-notification {
                top: -100px;
                left: 15px;
                right: 15px;
                justify-content: space-between;
            }
            .kaido-top-notification.show {
                top: 15px; 
            }
        }
    </style>
</head>
<body>
    <!--TRANG LOADING-->
    <div id="loading">
        <img src="image/imgload.png">
        <p>Đang tải, gần xong rồi!!<span id="dots">...</span></p>
    </div>
    <div id="dautrang"> 
    <div class="header">
            <img class="logo" src="image/kaidologo.jpg">
            <h2>Tài khoản và Mua hàng</h2>
            <div class="nut0">
                <a href="index.php" onclick="clickSound()">Trang chủ</a>
            </div>
            <div class="nut1">
                <a href="khac/khac.php" onclick="clickSound()">Dự án khác</a>
            </div>
            <div class="nut2">
                <a href="hoithem.php" onclick="clickSound()">Trò chuyện</a>
            </div>
            <div class="nut4chon">
                <a href="tienich.php" onclick="clickSound()">Tài khoản/Mua hàng</a>
            </div>
            <div class="nut5">
                <a href="kythuat/details.php">Chi tiết kỹ thuật</a>
            </div>
            <div class="nut6">
                <a href="Blog/blog.php" onclick="clickSound()">Tin tức</a>
            </div>
            <div class="nut3">
                <a href="gamemini.php" onclick="clickSound()">Game mini</a>
            </div>

            <!-- NÚT BẤM CHUYỂN ĐỔI DARK / LIGHT MODE -->
            <button id="themeToggle" class="theme-toggle-btn" onclick="toggleTheme()">
                🌙 Dark
            </button>
    </div>
<!--Nội dung chính-->
    <h3 class="text">Tài khoản và Mua hàng</h3>
<div class="tienich-grid">
    <div class="tienich-card">
        <!-- Gọi hàm kiểm tra đăng nhập khi bấm vào Tài khoản của bạn -->
        <h3><a href="Taikhoan/taikhoan.php" onclick="handleAuthClick(event, this.href)">Tài khoản của bạn</a></h3>
        <p>Thông tin tài khoản của bạn</p>
    </div>

    <div class="tienich-card">
        <h3>
            <a href="sanpham/shop.php">TRUNG TÂM CỬA HÀNG LINH KIỆN & THIẾT BỊ ĐIỆN TỬ</a>
        </h3>
        <p>Ghé thăm mua sắm ngay tại hệ thống mua bán linh kiện điện tử, mô đun các loại, động cơ nhỏ vừa và lớn, và các loại khác. </p>
    </div>

    <div class="tienich-card">
        <h3><a href="Giohang/giohang.php">Giỏ hàng</a></h3>
        <p>Quản lý các sản phẩm đã chọn trong cửa hàng</p>
    </div>

    <div class="tienich-card">
        <!-- Gọi hàm kiểm tra đăng nhập khi bấm vào Đơn hàng -->
        <h3><a href="donhang/donhang.php" onclick="handleAuthClick(event, this.href)">Đơn hàng</a></h3>
        <p>Theo dõi hành trình đơn hàng của bạn</p>
    </div>
</div>

<!--FOOTER-->
<div id="info">
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

<div class="quaylai">
    <a href="#dautrang">Đầu trang</a>
</div>

<!-- ĐẶT SCRIPT XỬ LÝ CHUNG CHO CẢ TÀI KHOẢN VÀ ĐƠN HÀNG -->
<script>
    // Nhận trạng thái đăng nhập từ PHP sang JavaScript
    const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;

    function handleAuthClick(event, targetUrl) {
        if (!isLoggedIn) {
            // Chặn không cho chuyển trang
            event.preventDefault();

            // Nếu đã có thông báo đang hiển thị thì không tạo chồng chéo
            if (document.getElementById('kaidoTopNotif')) return;

            // Tạo khung thông báo đẩy ở góc trên bên phải
            const notif = document.createElement('div');
            notif.id = 'kaidoTopNotif';
            notif.className = 'kaido-top-notification';
            notif.innerHTML = `
                <div>
                    <p><strong>⚠️ Thông báo</strong></p>
                    <p>Bạn chưa thể sử dụng mục này khi bạn chưa đăng nhập :(( !</p>
                </div>
                <a href="Taikhoan/dangnhap.php" class="login-quick-btn">Đăng nhập</a>
            `;
            document.body.appendChild(notif);

            // Kích hoạt hiệu ứng trượt xuống / hiện dần
            setTimeout(() => {
                notif.classList.add('show');
            }, 50);

            // Đợi đúng 5 giây (5000ms) thì cho hiệu ứng mờ dần và thu lại biến mất
            setTimeout(() => {
                notif.classList.remove('show');
                notif.style.opacity = '0';
                notif.style.top = '-100px';
                setTimeout(() => {
                    notif.remove();
                }, 500); // Khớp với thời gian transition CSS (0.5s)
            }, 5000);
        } else {
            // Nếu đã đăng nhập, cho phép chuyển sang trang đích bình thường
            window.location.href = targetUrl;
        }
    }
</script>

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
    <script src="js/slide.js"></script>
</html>