<?php
session_start(); // cần cho giỏ hàng (CSRF token)
// 1. Nhúng file kết nối cơ sở dữ liệu
require_once '../db.php'; // Điều chỉnh lại đường dẫn file db.php nếu cần (ví dụ: 'db.php')
require_once '../donhang/trangthai.php'; // dhToken(), h()

// [YÊU THÍCH] Lấy danh sách sản phẩm yêu thích của người đang đăng nhập
$yeuThich = [];
if (!empty($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $ys = mysqli_query($conn, "SELECT product_id FROM san_pham_yeu_thich WHERE user_id = $uid");
    if ($ys) {
        while ($y = mysqli_fetch_assoc($ys)) $yeuThich[(int)$y['product_id']] = true;
    }
}

// 2. Lấy tham số category_id và search từ URL (nếu có)
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 3. Lấy danh sách tất cả Danh mục để tạo các nút bấm
$cat_sql = "SELECT * FROM categories ORDER BY id ASC";
$cat_result = mysqli_query($conn, $cat_sql);

// 4. Xây dựng câu truy vấn SQL động dựa vào điều kiện Lọc & Tìm kiếm
$sql = "SELECT * FROM products WHERE 1=1";

if ($category_id > 0) {
    $sql .= " AND category_id = " . $category_id;
}

if (!empty($search)) {
    $search_clean = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (product_name LIKE '%$search_clean%' OR description LIKE '%$search_clean%')";
}

$sql .= " ORDER BY id DESC";
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
    <!-- CHÚ Ý: CSS RIÊNG CHO THANH LỌC TÌM KIẾM -->
    <style>
        /* CSS cho Thanh Tìm kiếm và Nút Phân loại */
        .filter-section {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 15px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: center;
        }

        /* Ô tìm kiếm */
        .search-box {
            display: flex;
            gap: 10px;
            width: 100%;
            max-width: 500px;
        }
        .search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 25px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.3s;
        }
        .search-box input:focus {
            border-color: #ff5722;
        }
        .search-box button {
            padding: 10px 20px;
            background: #ff5722;
            color: #fff;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }
        .search-box button:hover {
            background: #e64a19;
        }

       /* Hàng nút bấm phân loại - Chuyển sang lướt ngang gọn gàng */
        .category-buttons {
            display: flex;
            flex-wrap: nowrap; /* Không cho xuống hàng */
            gap: 10px;
            overflow-x: auto;  /* Cho phép cuộn ngang khi vượt quá màn hình */
            width: 100%;
            max-width: 800px;
            padding: 8px 5px 12px 5px; /* Thêm padding đáy để không bị sát thanh cuộn */
            scroll-behavior: smooth; /* Cuộn mượt mà */
            /* Tùy chỉnh thanh cuộn cho đẹp mắt trên trình duyệt Webkit (Chrome, Safari, Edge) */
            scrollbar-width: thin; /* Cho Firefox */
            scrollbar-color: #ff5722 #f1f1f1;
        }

        /* Tùy chỉnh thanh cuộn ngang (Scrollbar) */
        .category-buttons::-webkit-scrollbar {
            height: 6px;
        }
        .category-buttons::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .category-buttons::-webkit-scrollbar-thumb {
            background: #ff5722;
            border-radius: 10px;
        }
        .category-buttons::-webkit-scrollbar-thumb:hover {
            background: #e64a19;
        }

        .cat-btn {
            padding: 8px 18px;
            background-color: #f1f1f1;
            color: #333;
            text-decoration: none;
            border-radius: 20px;
            font-weight: 500;
            border: 1px solid #ccc;
            white-space: nowrap; /* BẮT BUỘC: Giữ tên danh mục không bị bẻ chữ xuống dòng */
            flex-shrink: 0;      /* Không cho các nút bị bóp méo chiều rộng */
            transition: all 0.3s ease;
        }
        .cat-btn:hover, .cat-btn.active {
            background-color: #ff5722;
            color: #fff;
            border-color: #ff5722;
        }

        /* Nút thêm vào giỏ trên thẻ sản phẩm */
        .add-cart-form { margin-top: 10px; }
        .add-cart-btn {
            padding: 8px 16px;
            background: #ff5722;
            color: #fff;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }
        .add-cart-btn:hover { background: #e64a19; }

        /* [YÊU THÍCH] Nút trái tim */
        .fav-form { margin-top: 8px; }
        .fav-btn {
            padding: 7px 16px;
            background: transparent;
            color: #e91e63;
            border: 2px solid #e91e63;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        .fav-btn:hover, .fav-btn.on { background: #e91e63; color: #fff; }

        /* Thông báo "Người dùng" */
.user-header-badge {
    background: rgba(0, 0, 0, 0.55); /* Nền đen mờ */
    backdrop-filter: blur(8px); /* Hiệu ứng làm mờ kính mờ */
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 20px;
    padding: 6px 14px;
    display: inline-flex;
    align-items: center;
    color: #f1f5f9;
    font-size: 13px;
    margin-left: 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    white-space: nowrap;
}

.user-header-badge strong {
    color: #ff416c; /* Tô điểm tên user màu hồng cam sáng */
    margin-left: 4px;
}

.user-header-badge.visitor a {
    color: #38bdf8;
    text-decoration: none;
    font-weight: bold;
}

.user-header-badge.visitor a:hover {
    text-decoration: underline;
}
    </style>
</head>
<body>

<!-- TRANG LOADING -->
    <div id="loading">
        <img src="../image/imgload.png">
        <p>Đang tải, gần xong rồi!!<span id="dots">...</span></p>
    </div>

    <!-- THANH MÀU XÁM (Đầu trang - Smart Header: Tự ẩn khi lướt xuống, hiện khi lướt lên) -->
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

<h1>TRUNG TÂM CỬA HÀNG LINH KIỆN & THIẾT BỊ ĐIỆN TỬ</h1>

<!-- KAIDO PROMOTION CAROUSEL -->
<div class="kaido-carousel">
    <div class="kaido-slides">
        <div class="kaido-slide active">
            <img src="../image/xev5trinhchieu.jpg" alt="KAIDO">
            <div class="kaido-banner-content">
                <span>XE TẢI RC</span>
                <h2>LINH KIỆN CHẤT LƯỢNG, DỰ ÁN SIÊU BỀN</h2>
                <p>Với chiếc xe tải RC V5 với nhiều công nghệ nâng cấp nhờ những linh kiện chất lượng cao được bán tại trung tâm cửa hàng linh kiện của chúng tôi!.</p>
                <a href="../index.php">XEM NGAY</a>
            </div>
        </div>
        <div class="kaido-slide">
            <img src="../image/loatrinhchieu.jpg" alt="KAIDO DIY">
            <div class="kaido-banner-content">
                <span>KAIDO DIY</span>
                <h2>DIY — TẬN DỤNG & SÁNG TẠO</h2>
                <p>Biến linh kiện cũ thành những sản phẩm hữu ích.</p>
                <a href="../khac/khac.php">KHÁM PHÁ</a>
            </div>
        </div>
        <div class="kaido-slide">
            <img src="../image/chiptrinhchieu.jpg" alt="KAIDO RC">
            <div class="kaido-banner-content">
                <span>DIY COMPONENTS</span>
                <h2>LINH KIỆN BAO LA, GIÁ DỄ TIẾP CẬN, HÀNG CHÍNH HÃNG MỚI, BỀN BỈ VÔ ĐỐI</h2>
                <p>Yên tâm xây dựng với những linh kiện mới, chính hãng chất lượng cao cho dự án DIY của riêng bạn.</p>
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

    <!-- ==================== KHU VỰC TÌM KIẾM & PHÂN LOẠI ==================== -->
    <div class="filter-section">
        <!-- 1.2 Tìm kiếm sản phẩm -->
        <form action="shop.php#dautrang" method="GET" class="search-box">
            <?php if ($category_id > 0): ?>
                <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Nhập tên sản phẩm cần tìm..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Tìm kiếm</button>
        </form>

        <!-- 1.2 Phân loại sản phẩm -->
        <div class="category-buttons">
            <!-- Nút Tất cả -->
            <a href="shop.php#dautrang" class="cat-btn <?php echo ($category_id == 0) ? 'active' : ''; ?>">
                Tất cả
            </a>

            <!-- Lấy các danh mục từ bảng categories -->
            <?php if ($cat_result && mysqli_num_rows($cat_result) > 0): ?>
                <?php while ($cat = mysqli_fetch_assoc($cat_result)): ?>
                    <a href="shop.php?category_id=<?php echo $cat['id']; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>#dautrang" 
                       class="cat-btn <?php echo ($category_id == $cat['id']) ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </a>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <h3 class="text">
        <?php 
        if (!empty($search)) {
            echo "Kết quả tìm kiếm cho: \"" . htmlspecialchars($search) . "\"";
        } else {
            echo "Danh sách sản phẩm";
        }
        ?>
    </h3>

    <div class="shop-list">
        <?php
if ($result && mysqli_num_rows($result) > 0):
    while ($row = mysqli_fetch_assoc($result)):
        if ($row['status'] !== 'active') continue;   // sản phẩm inactive không hiện trên cửa hàng

        $status_text = "Còn hàng";
        if ($row['stock_qty'] <= 0) {
            $status_text = "Đã hết hàng";
        }
        $img = trim($row['image_path'] ?? '');
?>
    <article class="product-card">
        <div class="product-image">
            <?php if ($img !== '' && file_exists(__DIR__ . '/../image/uploads/' . $img)): ?>
                <img src="../image/uploads/<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($row['product_name']); ?>">
            <?php else: ?>
                <div class="no-image">Chưa có ảnh minh họa</div>
            <?php endif; ?>
        </div>
        <h2><?php echo htmlspecialchars($row['product_name']); ?></h2>
        <p><?php echo htmlspecialchars($row['description']); ?></p>
        <p>Giá: <strong><?php echo number_format($row['price'], 0, ',', '.'); ?> VNĐ</strong></p>
        <p>Tình trạng: <?php echo $status_text; ?></p>
        <a href="chitietsanpham.php?id=<?php echo $row['id']; ?>">Xem chi tiết</a>
        <?php $daThich = isset($yeuThich[(int)$row['id']]); ?>
        <form class="fav-form" action="xuly_yeuthich.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo h(dhToken()); ?>">
            <input type="hidden" name="product_id" value="<?php echo (int)$row['id']; ?>">
            <input type="hidden" name="action" value="<?php echo $daThich ? 'remove' : 'add'; ?>">
            <input type="hidden" name="return_page" value="shop">
            <button class="fav-btn <?php echo $daThich ? 'on' : ''; ?>" type="submit"><?php echo $daThich ? '♥ Bỏ yêu thích' : '♡ Yêu thích'; ?></button>
        </form>
        <?php if ($row['status'] == 'active' && $row['stock_qty'] > 0): ?>
        <form class="add-cart-form" action="../Giohang/them_gio.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo h(dhToken()); ?>">
            <input type="hidden" name="product_id" value="<?php echo (int)$row['id']; ?>">
            <input type="hidden" name="so_luong" value="1">
            <button class="add-cart-btn" type="submit">🛒 Thêm vào giỏ</button>
        </form>
        <?php endif; ?>
    </article>
<?php
    endwhile;
else:
?>
    <p style="text-align: center; width: 100%;">Không tìm thấy sản phẩm nào phù hợp.</p>
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
        <a href="../Giohang/giohang.php">Giỏ hàng của bạn</a>
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