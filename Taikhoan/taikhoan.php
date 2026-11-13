<?php
session_start();
require_once "../db.php";

// Nếu ĐÃ ĐĂNG NHẬP -> Hiển thị trang quản lý tài khoản
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        session_destroy();
        header("Location: taikhoan.php");
        exit;
    }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài khoản của bạn</title>
    <link rel="stylesheet" href="../css/dndk.css">
</head>
<body class="account-page">
    <!-- THANH MÀU XÁM (Đầu trang - Smart Header: Tự ẩn khi lướt xuống, hiện khi lướt lên) -->
    <div class="header" id="mainHeader">
        <div class="header-left">
            <img class="logo" src="../image/kaidologo.jpg">
            <h2>Tài khoản của bạn</h2>
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
    <div id="dautrang"> 
        <div class="nav-container">
            <div class="nav-scroll-wrapper">
                <div class="nut0">
                    <a href="../index.php" onclick="clickSound()">Trang chủ</a>
                </div>
                <div class="nut1">
                    <a href="../khac/khac.php">Dự án khác</a>
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
    </div>

    <div class="account-container">
        <div class="account-header">
            <h1>Tài khoản của bạn</h1>
            <p>Quản lý thông tin tài khoản Kaido</p>
        </div>

        <div class="account-card">
            <h2>Thông tin tài khoản</h2>
           <div class="account-info">
                <div class="info-row">
                    <span>Tên đăng nhập</span>
                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                </div>
                <div class="info-row">
                    <span>Họ và tên</span>
                    <strong><?php echo htmlspecialchars($user['full_name'] ?? '') ?: 'Chưa cập nhật'; ?></strong>
                </div>
                <div class="info-row">
                    <span>Email</span>
                    <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                </div>
                <div class="info-row">
                    <span>Số điện thoại</span>
                    <strong><?php echo htmlspecialchars($user['phone'] ?? '') ?: 'Chưa cập nhật'; ?></strong>
                </div>
                <div class="info-row">
                    <span>Vai trò</span>
                    <strong><?php echo htmlspecialchars($user['role']); ?></strong>
                </div>
                <div class="info-row">
                    <span>Ngày tham gia</span>
                    <strong><?php echo !empty($user['created_at']) ? date("d/m/Y", strtotime($user['created_at'])) : 'Chưa rõ'; ?></strong>
                </div>
            </div>
        </div>

        <div class="account-card">
            <h2>Chức năng tài khoản</h2>
            <div class="account-actions">
                <button><a href="../Chinhsuathongtin/chinhsuathongtin.php" style="color: inherit; text-decoration: none;">Chỉnh sửa thông tin</a></button>
                <button><a href="../donhang/donhang.php" style="color: inherit; text-decoration: none;">Theo dõi đơn hàng</a></button>
                <button><a href="../Giohang/giohang.php" style="color: inherit; text-decoration: none;">Giỏ hàng</a></button>
                <button><a href="../Doimk/doimk.php" style="color: inherit; text-decoration: none;">Đổi mật khẩu</a></button>
                <button><a href="../sanpham/yeuthich.php" style="color: inherit; text-decoration: none;">Sản phẩm yêu thích</a></button>
                <button><a href="../donhang/lichsu.php" style="color: inherit; text-decoration: none;">Lịch sử mua hàng</a></button>
            </div>
        </div>

        <div class="account-footer">
            <a href="../tienich.php">← Trở về</a>
            <a href="xoataikhoan.php">Xóa tài khoản</a>
            <a href="dangxuat.php">Đăng xuất</a>
        </div>
    </div>
    <!--SCRIPT CHUYỂN ĐỔI GIAO DIỆN SÁNG/TỐI-->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const savedTheme = localStorage.getItem("kaido_theme");
            const themeBtn = document.getElementById("themeToggle");

            if (savedTheme === "light") {
                document.body.classList.add("light-mode");
                if (themeBtn) themeBtn.innerHTML = "☀️ Light";
            } else {
                document.body.classList.remove("light-mode");
                if (themeBtn) themeBtn.innerHTML = "🌙 Dark";
            }
        });

        function toggleTheme() {
            const body = document.body;
            const themeBtn = document.getElementById("themeToggle");

            body.classList.toggle("light-mode");

            if (body.classList.contains("light-mode")) {
                localStorage.setItem("kaido_theme", "light");
                if (themeBtn) themeBtn.innerHTML = "☀️ Light";
            } else {
                localStorage.setItem("kaido_theme", "dark");
                if (themeBtn) themeBtn.innerHTML = "🌙 Dark";
            }
        }
    </script>
</body>
</html>

<?php 
// KẾT THÚC KHỐI IF: Dấu đóng ngoặc nhọn này ứng với `if (isset($_SESSION['user_id']))`
} else {
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../image/kaidoLogomini.png">
    <title>Kaido Shop</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
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
        }

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
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const notif = document.createElement('div');
            notif.className = 'kaido-top-notification';
            notif.innerHTML = `
                <div>
                    <p><strong>⚠️ Thông báo</strong></p>
                    <p>Bạn chưa đăng nhập tài khoản!</p>
                </div>
                <a href="../Taikhoan/dangnhap.php" class="login-quick-btn">Đăng nhập</a>
            `;
            document.body.appendChild(notif);

            setTimeout(() => {
                notif.classList.add('show');
            }, 100);

            setTimeout(() => {
                notif.style.opacity = '0';
                notif.style.top = '-100px';
                setTimeout(() => {
                    notif.remove();
                }, 500);
            }, 5000);
        });
    </script>

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
</html>
<?php
} 
?>