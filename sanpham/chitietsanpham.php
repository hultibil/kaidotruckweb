<?php
session_start(); // cần cho giỏ hàng (CSRF token)
// 1. Nhúng file kết nối cơ sở dữ liệu
require_once '../db.php'; // Điều chỉnh lại đường dẫn file db.php nếu cần
require_once '../donhang/trangthai.php'; // dhToken(), h()
require_once __DIR__ . '/danhgia_helper.php'; // [ĐÁNH GIÁ] rv_stars(), rv_media_url()...

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

// [ĐÁNH GIÁ] Lấy tóm tắt + danh sách đánh giá đang hiển thị của sản phẩm
$rvPhanBo = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$rvTong = 0; $rvTB = 0.0;
$rvDs = []; $rvMedia = [];
$rvMoiTrang = 6;
$rvTrang = max(1, (int)($_GET['rvp'] ?? 1));
$rvSoTrang = 1;
$rvDonDuocDanhGia = 0;   // mã đơn đã giao có chứa sản phẩm này (0 = chưa mua / chưa giao)
$rvDaDanhGia = false;
$rvLoi = false;
try {
    $st = $conn->prepare("SELECT rating, COUNT(*) AS c FROM product_reviews WHERE product_id = ? AND status = 'visible' GROUP BY rating");
    $st->bind_param('i', $product_id);
    $st->execute();
    $sum = 0;
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $rvPhanBo[(int)$r['rating']] = (int)$r['c'];
        $rvTong += (int)$r['c'];
        $sum += (int)$r['rating'] * (int)$r['c'];
    }
    $st->close();
    $rvTB = $rvTong > 0 ? $sum / $rvTong : 0.0;

    $rvSoTrang = max(1, (int)ceil($rvTong / $rvMoiTrang));
    $rvTrang = min($rvTrang, $rvSoTrang);
    $off = ($rvTrang - 1) * $rvMoiTrang;
    $st = $conn->prepare("SELECT r.id, r.rating, r.comment, r.created_at, u.username
                          FROM product_reviews r JOIN users u ON u.id = r.user_id
                          WHERE r.product_id = ? AND r.status = 'visible'
                          ORDER BY r.created_at DESC, r.id DESC LIMIT ? OFFSET ?");
    $st->bind_param('iii', $product_id, $rvMoiTrang, $off);
    $st->execute();
    $rvDs = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    if ($rvDs) {
        $ids = implode(',', array_map(fn($r) => (int)$r['id'], $rvDs));
        $rm = $conn->query("SELECT review_id, file_path, media_type FROM review_media WHERE review_id IN ($ids) ORDER BY id");
        foreach ($rm->fetch_all(MYSQLI_ASSOC) as $m) {
            $rvMedia[(int)$m['review_id']][] = $m;
        }
    }

    if (!empty($_SESSION['user_id'])) {
        $uidRv = (int)$_SESSION['user_id'];
        $st = $conn->prepare("SELECT MAX(o.id) AS oid FROM orders o JOIN order_items oi ON oi.order_id = o.id
                              WHERE o.user_id = ? AND o.status = 'completed' AND oi.product_id = ?");
        $st->bind_param('ii', $uidRv, $product_id);
        $st->execute();
        $rvDonDuocDanhGia = (int)($st->get_result()->fetch_assoc()['oid'] ?? 0);
        $st->close();
        if ($rvDonDuocDanhGia) {
            $st = $conn->prepare('SELECT 1 FROM product_reviews WHERE user_id = ? AND product_id = ? LIMIT 1');
            $st->bind_param('ii', $uidRv, $product_id);
            $st->execute();
            $rvDaDanhGia = (bool)$st->get_result()->fetch_assoc();
            $st->close();
        }
    }
} catch (Throwable $e) {
    $rvLoi = true;
    error_log('chitietsanpham/danhgia: ' . $e->getMessage()); // chưa chạy danhgia.sql thì ẩn khu đánh giá
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../image/kaidoLogomini.png">
    <title><?php echo htmlspecialchars($product['product_name']); ?> - Kaido Shop</title>
    <link rel="stylesheet" href="../css/shop.css">
    <link rel="stylesheet" href="../css/darklight.css">
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

        /* [ĐÁNH GIÁ] */
        .review-section { width: 100%; margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px; }
        .rv-summary { display: flex; flex-wrap: wrap; gap: 24px; align-items: center; margin: 10px 0 18px; }
        .rv-avg { text-align: center; min-width: 110px; }
        .rv-avg .num { font-size: 44px; font-weight: 800; line-height: 1; }
        html body.glassy-page .glassy-content .rv-star-on { color: #f59e0b !important; letter-spacing: 2px; }
        .rv-bars { flex: 1; min-width: 220px; }
        .rv-bar { display: flex; align-items: center; gap: 8px; font-size: 13px; margin: 3px 0; }
        .rv-bar .track { flex: 1; height: 8px; border-radius: 99px; background: rgba(120,150,190,.3); overflow: hidden; }
        .rv-bar .fill { display: block; height: 100%; background: #f59e0b; }
        .rv-item { padding: 14px 0; border-top: 1px solid rgba(120,150,190,.3); }
        .rv-item .who { font-weight: 700; }
        .rv-item .when { font-size: 12px; opacity: .75; margin-left: 8px; }
        .rv-badge { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 99px; background: rgba(16,185,129,.2); margin-left: 8px; }
        .rv-media { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        .rv-media img, .rv-media video { width: 96px; height: 96px; object-fit: cover; border-radius: 10px; border: 1px solid rgba(120,150,190,.4); background: #000; }
        .rv-media video { width: 170px; }
        .rv-pager { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
        html body.glassy-page .glassy-content .rv-pager a, html body.glassy-page .glassy-content a.rv-write { display: inline-block; padding: 7px 14px; border-radius: 99px; font-weight: 700; text-decoration: none; background: var(--paper, #fff) !important; color: var(--accent, #0756a8) !important; border: 1px solid var(--field-border, #8eb5db); }
        html body.glassy-page .glassy-content .rv-pager a.cur, html body.glassy-page .glassy-content a.rv-write { background: var(--primary-gradient, #0861c3) !important; color: #fff !important; border-color: transparent; }

        .back-btn {
            display: inline-block;
            margin-bottom: 15px;
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        /* Hỗ trợ Light Mode */
        body.light-mode { background-color: #f1f5f9 !important; color: #0f172a !important; }
        body.light-mode .product-detail-container { background: #ffffff !important; border: 1px solid #cbd5e1 !important; color: #0f172a !important; box-shadow: 0 4px 15px rgba(0,0,0,0.05) !important; }
        body.light-mode .detail-info h1, body.light-mode .description-box h3, body.light-mode .specs-section h3 { color: #0f172a !important; }
        body.light-mode .specs-table th { background-color: #f8fafc !important; color: #475569 !important; }
        body.light-mode .specs-table th, body.light-mode .specs-table td { border-color: #e2e8f0 !important; color: #1e293b !important; }
        body.light-mode .description-box { border-top-color: #e2e8f0 !important; }
        body.light-mode .theme-toggle-btn { background: rgba(0,0,0,0.08) !important; border: 1px solid rgba(0,0,0,0.2) !important; color: #0f172a !important; }
    </style>
<link rel="stylesheet" href="../css/glassy.css">
<script src="../js/glassy-theme.js"></script>
<script src="../js/shop-actions.js" defer></script>
</head>
<body class="glassy-page">
<?php $glassyBase = '../'; require __DIR__ . '/../layout/glassy-sidebar.php'; ?>
<div id="glassy-content" class="glassy-content" tabindex="-1">

<!-- THANH MÀU XÁM (Đầu trang - Smart Header: Tự ẩn khi lướt xuống, hiện khi lướt lên) -->
    <div class="header" id="mainHeader">
        <div class="header-left">
            <img class="logo" src="../image/kaidologo.jpg">
            <h2>Dự án khác</h2>
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

    <!-- [ĐÁNH GIÁ] Đánh giá của khách đã mua -->
    <?php if (!$rvLoi): ?>
    <div class="review-section" id="danhgia">
        <h3>Đánh giá sản phẩm</h3>

        <?php if ($rvDonDuocDanhGia): ?>
            <p><a class="rv-write" href="danhgia.php?order_id=<?php echo (int)$rvDonDuocDanhGia; ?>&amp;product_id=<?php echo (int)$product['id']; ?>"><?php echo $rvDaDanhGia ? '✏️ Sửa đánh giá của bạn' : '⭐ Viết đánh giá'; ?></a></p>
        <?php endif; ?>

        <?php if ($rvTong === 0): ?>
            <p>Chưa có đánh giá nào cho sản phẩm này.</p>
        <?php else: ?>
            <div class="rv-summary">
                <div class="rv-avg">
                    <div class="num"><?php echo number_format($rvTB, 1, ',', ''); ?></div>
                    <div class="rv-star-on"><?php echo h(rv_stars((int)round($rvTB))); ?></div>
                    <div style="font-size:13px"><?php echo (int)$rvTong; ?> đánh giá</div>
                </div>
                <div class="rv-bars">
                    <?php for ($i = 5; $i >= 1; $i--): $pc = $rvTong ? round($rvPhanBo[$i] * 100 / $rvTong) : 0; ?>
                        <div class="rv-bar"><span><?php echo $i; ?> ★</span><span class="track"><span class="fill" style="width:<?php echo (int)$pc; ?>%"></span></span><span><?php echo (int)$rvPhanBo[$i]; ?></span></div>
                    <?php endfor; ?>
                </div>
            </div>

            <?php foreach ($rvDs as $rv): ?>
                <div class="rv-item">
                    <div><span class="who"><?php echo h(rv_mask_name($rv['username'])); ?></span><span class="rv-badge">✓ Đã mua hàng</span><span class="when"><?php echo h(date('d/m/Y', strtotime($rv['created_at']))); ?></span></div>
                    <div class="rv-star-on"><?php echo h(rv_stars((int)$rv['rating'])); ?></div>
                    <?php if (trim((string)$rv['comment']) !== ''): ?><p style="margin:6px 0 0"><?php echo nl2br(h($rv['comment'])); ?></p><?php endif; ?>
                    <?php if (!empty($rvMedia[(int)$rv['id']])): ?>
                        <div class="rv-media">
                            <?php foreach ($rvMedia[(int)$rv['id']] as $m): ?>
                                <?php if ($m['media_type'] === 'video'): ?>
                                    <video src="<?php echo h(rv_media_url($m['file_path'])); ?>" controls preload="metadata"></video>
                                <?php else: ?>
                                    <a href="<?php echo h(rv_media_url($m['file_path'])); ?>" target="_blank" rel="noopener"><img src="<?php echo h(rv_media_url($m['file_path'])); ?>" alt="Ảnh đánh giá" loading="lazy"></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if ($rvSoTrang > 1): ?>
                <div class="rv-pager">
                    <?php for ($i = 1; $i <= $rvSoTrang; $i++): ?>
                        <a class="<?php echo $i === $rvTrang ? 'cur' : ''; ?>" href="chitietsanpham.php?id=<?php echo (int)$product['id']; ?>&amp;rvp=<?php echo $i; ?>#danhgia"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<!-- ==================== KHU VỰC FOOTER ==================== -->
<div class="thongtinweb">
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
</div>

<div class="quaylai">
    <a href="#dautrang">Đầu trang</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', initTheme);

// Loading screen
window.addEventListener("load", function() {
    const loading = document.getElementById("loading");
    if (loading) {
        loading.style.opacity = "0";
        setTimeout(function() {
            loading.style.display = "none";
        }, 500);
    }
});
</script>
</div><!-- glassy-content -->
</body>
<script src="../js/slide.js"></script>
</html>
