<?php
session_start();

// Bắt buộc phải đăng nhập mới được vào trang này
if (!isset($_SESSION['user_id'])) {
    header("Location: dangnhap.php");
    exit;
}

// Tạo CSRF token nếu chưa có để chống tấn công giả mạo request
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác nhận xóa tài khoản - KAIDO</title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="../css/dndk.css">
    <link rel="stylesheet" href="../css/xoataikhoan.css">
</head>
<body class="account-page">

    <!-- Nút chuyển đổi Dark / Light Mode góc trên -->
    <button id="themeToggle" class="auth-theme-btn" onclick="toggleTheme()">
        🌙 Dark
    </button>

    <div class="account-container danger-zone-container">
        
        <!-- THẺ CẢNH BÁO NGUY HIỂM (DANGER CARD) -->
        <div class="account-card delete-card">
            
            <div class="delete-header">
                <div class="warning-icon">⚠️</div>
                <h1>Xóa tài khoản Kaido</h1>
                <p class="subtitle">Hành động này mang tính vĩnh viễn và không thể khôi phục</p>
            </div>

            <div class="warning-box">
                <strong>CẢNH BÁO NGUY HIỂM:</strong>
                <p>Bạn đang chuẩn bị xóa vĩnh viễn tài khoản Kaido của mình. Toàn bộ lịch sử mua hàng, danh sách yêu thích, thông tin cá nhân và giỏ hàng sẽ bị xóa hoàn toàn khỏi hệ thống.</p>
            </div>

            <!-- Hiển thị thông báo lỗi nếu có từ file xử lý gửi sang -->
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-toast">
                    ❌ <?= htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- FORM XÁC NHẬN -->
            <form
                action="xulyxoataikhoan.php"
                method="POST"
                class="delete-form"
                onsubmit="return confirm('⚠️ CẢNH BÁO CUỐI CÙNG:\n\nBạn có CHẮC CHẮN muốn xóa vĩnh viễn tài khoản Kaido này không?');"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="input-group">
                    <label for="password">Nhập mật khẩu hiện tại để xác nhận:</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <div class="delete-actions">
                    <button type="submit" class="btn-danger-confirm">
                        🔥 Xác nhận xóa tài khoản
                    </button>
                    <a href="taikhoan.php" class="btn-cancel">
                        ← Hủy & Quay lại
                    </a>
                </div>
            </form>

        </div>

    </div>

    <!-- Script xử lý Dark / Light mode ghi nhớ qua localStorage -->
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
                themeBtn.innerHTML = "☀️ Light";
            } else {
                localStorage.setItem("kaido_theme", "dark");
                themeBtn.innerHTML = "🌙 Dark";
            }
        }
    </script>
</body>
</html>