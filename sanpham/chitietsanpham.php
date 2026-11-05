<?php
session_start(); // cần cho giỏ hàng (CSRF token)
// 1. Nhúng file kết nối cơ sở dữ liệu
require_once '../db.php'; // Điều chỉnh lại đường dẫn file db.php nếu cần
require_once '../donhang/trangthai.php'; // dhToken(), h()

// [YÊU THÍCH] Kiểm tra người đang đăng nhập đã thích sản phẩm này chưa
$daThich = false;
if (!empty($_SESSION['user_id']) && isset($_GET['id'])) {
    $uid = (int)$_SESSION['user_id'];
    $pid = (int)$_GET['id'];
    $ys = mysqli_query($conn, "SELECT 1 FROM san_pham_yeu_thich WHERE user_id = $uid AND product_id = $pid LIMIT 1");
    if ($ys) $daThich = (mysqli_num_rows($ys) > 0);
}

// 2. Lấy ID sản phẩm từ URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    echo "<h3>Sản phẩm không tồn tại!</h3>";
    exit;
}

// 3. Truy vấn lấy thông tin sản phẩm và Tên danh mục (dùng JOIN)
$sql_product = "SELECT p.*, c.name AS category_name
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE p.id = $product_id LIMIT 1";
$res_product = mysqli_query($conn, $sql_product);
$product = mysqli_fetch_assoc($res_product);

if (!$product) {
    echo "<h3>Sản phẩm không tồn tại hoặc đã bị xóa!</h3>";
    exit;
}

// 4. Truy vấn lấy danh sách Thông số kỹ thuật của sản phẩm này
$sql_specs = "SELECT * FROM product_specs WHERE product_id = $product_id";
$res_specs = mysqli_query($conn, $sql_specs);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../image/kaidoLogomini.png">
    <title><?php echo htmlspecialchars($product['product_name']); ?> - Kaido Shop</title>
    <link rel="stylesheet" href="../css/shop.css">
    <style>
        /* CSS cho trang chi tiết sản phẩm */
        .product-detail-container {
            max-width: 1000px;
            margin: 30px auto;
            background: #2c2c2c;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
        }
        .detail-image {
            flex: 1;
            min-width: 300px;
            text-align: center;
        }
        .detail-image img {
            max-width: 100%;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .detail-info {
            flex: 1.5;
            min-width: 300px;
        }
        .detail-info h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        .category-badge {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .price {
            font-size: 26px;
            color: #ff5722;
            font-weight: bold;
            margin: 15px 0;
        }
        .stock-status {
            font-weight: bold;
            margin-bottom: 20px;
        }
        .in-stock { color: #2e7d32; }
        .out-stock { color: #d32f2f; }
        
        .description-box {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ccc;
            line-height: 1.6;
        }

        /* Bảng Thông số kỹ thuật */
        .specs-section {
            width: 100%;
            margin-top: 20px;
            border-top: 2px solid #eee;
            padding-top: 20px;
        }
        .specs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .specs-table th, .specs-table td {
            border: 1px solid #ddd;
            padding: 10px 15px;
            text-align: left;
        }
        .specs-table th {
            background-color: #020303;
            width: 30%;
        }

        /* Form thêm vào giỏ hàng */
        .add-cart-form {
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .add-cart-form input[type="number"] {
            width: 80px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }
        .add-cart-btn {
            padding: 10px 22px;
            background: #ff5722;
            color: #fff;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 15px;
            transition: background 0.3s;
        }
        .add-cart-btn:hover { background: #e64a19; }

        /* [YÊU THÍCH] Nút trái tim */
        .fav-form { margin-top: 12px; }
        .fav-btn {
            padding: 9px 22px;
            background: transparent;
            color: #e91e63;
            border: 2px solid #e91e63;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 15px;
            transition: all 0.3s;
        }
        .fav-btn:hover, .fav-btn.on { background: #e91e63; color: #fff; }

        .back-btn {
            display: inline-block;
            margin-bottom: 15px;
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<div class="header">
    <img class="logo" src="../image/kaidologo.jpg">
    <h2>Cửa hàng linh kiện Kaido</h2>
    <div class="nut0"><a href="../index.php">Trang chủ</a></div>
    <div class="nut4chon"><a href="shop.php">Cửa hàng</a></div>
</div>

<div style="max-width: 1000px; margin: 20px auto 0;">
    <a href="shop.php" class="back-btn">← Quay lại cửa hàng</a>
</div>

<!-- ==================== KHU VỰC CHI TIẾT SẢN PHẨM ==================== -->
<div class="product-detail-container">
    
    <!-- Ảnh sản phẩm -->
    <div class="detail-image">
    <?php $img = trim($product['image_path'] ?? ''); ?>
    <?php if ($img !== '' && file_exists(__DIR__ . '/../image/uploads/' . $img)): ?>
        <img src="../image/uploads/<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
    <?php else: ?>
        <div class="no-image">Chưa có ảnh minh họa</div>
    <?php endif; ?>
</div>

<div class="detail-info">
    <span class="category-badge">Danh mục: <?php echo htmlspecialchars($product['category_name']); ?></span>
    <h2><?php echo htmlspecialchars($product['product_name']); ?></h2>

    <div class="price"><?php echo number_format($product['price'], 0, ',', '.'); ?> VNĐ</div>

    <div class="stock-status">
        Tình trạng:
        <?php if ($product['stock_qty'] > 0 && $product['status'] == 'active'): ?>
            <span class="in-stock">Còn hàng (<?php echo $product['stock_qty']; ?> sản phẩm)</span>
        <?php else: ?>
            <span class="out-stock">Hết hàng</span>
        <?php endif; ?>
    </div>

    <?php if ($product['stock_qty'] > 0 && $product['status'] == 'active'): ?>
    <form class="add-cart-form" action="../Giohang/them_gio.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo h(dhToken()); ?>">
        <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
        <label for="so_luong">Số lượng:</label>
        <input type="number" id="so_luong" name="so_luong" value="1" min="1" max="<?php echo min(99, (int)$product['stock_qty']); ?>">
        <button class="add-cart-btn" type="submit">🛒 Thêm vào giỏ hàng</button>
    </form>
    <?php endif; ?>

    <!-- [YÊU THÍCH] -->
    <form class="fav-form" action="xuly_yeuthich.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo h(dhToken()); ?>">
        <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
        <input type="hidden" name="action" value="<?php echo $daThich ? 'remove' : 'add'; ?>">
        <input type="hidden" name="return_page" value="detail">
        <button class="fav-btn <?php echo $daThich ? 'on' : ''; ?>" type="submit"><?php echo $daThich ? '♥ Bỏ yêu thích' : '♡ Thêm vào yêu thích'; ?></button>
    </form>

    <div class="description-box">
        <h3>Mô tả sản phẩm:</h3>
        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
    </div>
</div>

    <!-- Thông số kỹ thuật (Lấy từ bảng product_specs) -->
    <div class="specs-section">
        <h3>Thông số kỹ thuật</h3>
        <?php if ($res_specs && mysqli_num_rows($res_specs) > 0): ?>
            <table class="specs-table">
                <tbody>
                    <?php while ($spec = mysqli_fetch_assoc($res_specs)): ?>
                        <tr>
                            <th><?php echo htmlspecialchars($spec['spec_name']); ?></th>
                            <td><?php echo htmlspecialchars($spec['spec_value']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Chưa có thông số kỹ thuật chi tiết cho sản phẩm này.</p>
        <?php endif; ?>
    </div>

</div>

<!-- ==================== KHU VỰC FOOTER ==================== -->
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
    <a href="#dautrang">Đầu trang</a>
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
    <script src="css/slide.js"></script>
</html>