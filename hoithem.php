<?php
session_start(); // Bắt đầu session để quản lý đăng nhập
require_once __DIR__ . '/donhang/trangthai.php'; // dhToken() cho nút "+ Giỏ" trong chat AI
if (empty($_SESSION['ai_csrf'])) { $_SESSION['ai_csrf'] = bin2hex(random_bytes(32)); }
?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Trò chuyện</title>
        <link rel="icon" href="image/kaidoLogomini.png">
        <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/glassy.css">
<script src="js/glassy-theme.js"></script>
<style>
/* [AI] Thẻ sản phẩm trong chatbox */
.chatbox .ai-text{white-space:pre-wrap;word-break:break-word}
.chatbox .ai-cards{display:grid;gap:8px;margin-top:10px}
.chatbox .ai-card{display:flex;gap:10px;align-items:center;padding:8px;border-radius:12px;background:rgba(255,255,255,.12);border:1px solid rgba(128,160,200,.35)}
.chatbox .ai-card img{width:64px;height:64px;object-fit:cover;border-radius:10px;flex:0 0 64px}
.chatbox .ai-card-body{min-width:0;flex:1}
.chatbox .ai-card-name{display:block;font-weight:700;text-decoration:none;word-break:break-word}
.chatbox .ai-card-price{font-size:13px;margin:2px 0 6px;opacity:.9}
.chatbox .ai-card-actions{display:flex;gap:6px;flex-wrap:wrap}
.chatbox .ai-btn{padding:5px 12px;border-radius:99px;border:1px solid rgba(128,160,200,.6);background:transparent;font:inherit;font-size:13px;cursor:pointer;text-decoration:none}
.chatbox .ai-btn-main{background:linear-gradient(135deg,#ff416c,#ff4b2b);border-color:transparent;color:#fff}
.chatbox .ai-btn:disabled{opacity:.6;cursor:default}
.chatbox .ai-failed{opacity:.85}
.chatbox .ai-typing{opacity:.7;font-style:italic}
html body.glassy-page .chatbox .ai-card-name,html body.glassy-page .chatbox .ai-card-price{color:var(--ink)!important}
html body.glassy-page .chatbox .ai-btn{color:var(--ink)!important}
html body.glassy-page .chatbox .ai-btn-main{color:#fff!important;-webkit-text-fill-color:#fff}
</style>
</head>
    <body class="glassy-page">
<?php $glassyBase = ''; require __DIR__ . '/layout/glassy-sidebar.php'; ?>
<div id="glassy-content" class="glassy-content" tabindex="-1">
        <!-- TRANG LOADING -->
    <div id="loading">
        <img src="image/imgload.png">
        <p>Đang tải, gần xong rồi!!<span id="dots">...</span></p>
    </div>

    <!-- THANH MÀU XÁM (Đầu trang - Smart Header: Tự ẩn khi lướt xuống, hiện khi lướt lên) -->
    <div class="header" id="mainHeader">
        <div class="header-left">
            <img class="logo" src="image/kaidologo.jpg">
            <h2>Trò chuyện</h2>
        </div>
        <div class="header-right">
            <!-- NÚT BẤM CHUYỂN ĐỔI DARK / LIGHT MODE -->


            <!-- HIỂN THỊ TÊN TÀI KHOẢN HOẶC ĐĂNG NHẬP -->
            <?php if (isset($_SESSION['username'])): ?>
                <div class="user-header-badge">
                    <span>👋 Hi, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
                </div>
            <?php else: ?>
                <div class="user-header-badge visitor">
                    <a href="Taikhoan/dangnhap.php">🔑 Đăng nhập/ đăng ký</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- THANH ĐIỀU HƯỚNG MỚI (Nằm dưới thanh màu xám, không ghim, lướt ngang trên mobile) -->
    <div id="dautrang"> 
    <div class="nav-container">
        <div class="nav-scroll-wrapper">
            <div class="nut0">
                <a href="index.php" onclick="clickSound()">Trang chủ</a>
            </div>
            <div class="nut1">
                <a href="khac/khac.php" onclick="clickSound()">Dự án khác</a>
            </div>
            <div class="nut2chon">
                <a>Trò chuyện</a>
            </div>
            <div class="nut4">
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
        </div>
    </div>

    </div>
    <h3 class="text">Trò chuyện</h3>
<!--Tro chuyen-->
<div class="chatbox">
        <div class="chat" id="chat">
            <div class="ai-message">
                <em>KAIDO AI</em>
                <p>
                    Xin chào!<br>
                    Mình là trợ lý AI của Kaido: tư vấn linh kiện (ví dụ "pin cho xe RC dưới 200k"),
                    và giải đáp về các mẫu xe của thương hiệu.
                    Bạn có thể nhập câu hỏi hoặc nhấn vào nút "Câu hỏi đề xuất".<br>
                </p>
            </div>
        </div>

        <div class="chat-input">

            <input
                type="text"
                id="question"
                placeholder="Hỏi về linh kiện hoặc xe Kaido… (vd: pin dưới 200k)" maxlength="500"
                onkeydown="if(event.key === 'Enter') sendMessage()"
            >

            <button onclick="sendMessage()">Gửi</button>

        </div>


        <button class="more-btn" onclick="showQuestions()">
            Câu hỏi đề xuất
        </button>


        <div id="questions" class="questions">

            <button onclick="askQuestion('Gợi ý cho mình pin dưới 200k')">
                Gợi ý pin dưới 200k
            </button>

            <button onclick="askQuestion('Mình mới làm xe RC, nên mua linh kiện gì?')">
                Linh kiện cho xe RC
            </button>

            <button onclick="askQuestion('KAIDO là gì?')">
                KAIDO là gì?
            </button>

            <button onclick="askQuestion('có những phiên bản xe nào?')">
                Có những phiên bản xe nào?
            </button>

            <button onclick="askQuestion('Xe công nông là gì?')">
                Xe công nông
            </button>

            <button onclick="askQuestion('Xe camera là gì?')">
                Xe camera
            </button>

            <button onclick="askQuestion('Xe V1 là gì?')">
                Xe V1
            </button>

            <button onclick="askQuestion('Xe V2 là gì?')">
                Xe V2
            </button>

            <button onclick="askQuestion('Xe V3 là gì?')">
                Xe V3
            </button>

            <button onclick="askQuestion('Xe V4 là gì?')">
                Xe V4
            </button>

            <button onclick="askQuestion('Xe địa hình là gì?')">
                Xe địa hình
            </button>

            <button onclick="askQuestion('Xe V5 là gì?')">
                Xe V5
            </button>

        </div>
        <button class="clear-btn" onclick="clearChat()">
            Xóa lịch sử
        </button>
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
</div><!-- glassy-content -->
</body>
    <script>
    window.KAIDO_AI = {
        api: 'ai/chat_api.php',
        csrf: <?= json_encode($_SESSION['ai_csrf']) ?>,
        cartApi: 'Giohang/them_gio.php',
        cartCsrf: <?= json_encode(dhToken()) ?>
    };
    </script>
    <script src="js/chat.js"></script>
    <script src="js/ai-chat.js"></script>
    <script src="js/slide.js"></script>
</html>