<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../donhang/trangthai.php';

// Thư mục chứa ảnh sản phẩm (giống shop.php: image/uploads/)
$THU_MUC_ANH = '../image/uploads/';
$errors = [];
$cart = $_SESSION['cart'] ?? [];
if (!is_array($cart)) $cart = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dhCheckToken();
    if (($_POST['action'] ?? '') === 'remove') {
        $id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        if ($id) unset($cart[$id]);
    } elseif (($_POST['action'] ?? '') === 'update') {
        $dsSoLuong = $_POST['quantity'] ?? [];
        if (!is_array($dsSoLuong)) $dsSoLuong = [];
        foreach ($dsSoLuong as $id => $qty) {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            $qty = filter_var($qty, FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) continue;
            if ($qty === false || $qty <= 0) unset($cart[$id]);
            else $cart[$id] = min($qty, 99);
        }
    }
    $_SESSION['cart'] = $cart;
    header('Location: giohang.php');
    exit;
}
$sanPham = [];
$tong = 0.0;
$stmt = $conn->prepare('SELECT id,sku,product_name,price,stock_qty,status,image_path FROM products WHERE id=?');
foreach ($cart as $key => $sl) {
    $id = filter_var($key, FILTER_VALIDATE_INT);
    $sl = filter_var($sl, FILTER_VALIDATE_INT);
    if (!$id || !$sl || $sl <= 0) { unset($cart[$key]); continue; }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $sp = $stmt->get_result()->fetch_assoc();
    if (!$sp || $sp['status'] !== 'active') {
        $errors[] = 'Một sản phẩm không còn được bán đã được loại khỏi giỏ.';
        unset($cart[$key]);
        continue;
    }
    if ((int)$sp['stock_qty'] < $sl) {
        $errors[] = 'Sản phẩm ' . $sp['product_name'] . ' chỉ còn ' . $sp['stock_qty'] . ' sản phẩm.';
    }
    $sp['so_luong'] = $sl;
    $sanPham[] = $sp;
    $tong += (float)$sp['price'] * $sl;
}
$_SESSION['cart'] = $cart;
$hopLe = count($sanPham) === count($cart) && count($sanPham) > 0;
foreach ($sanPham as $sp) {
    if ((int)$sp['stock_qty'] < (int)$sp['so_luong']) $hopLe = false;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng Kaido</title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="../css/cart.css">
    <style>
        /* Phần bổ sung cho giỏ hàng động (có thể chuyển sang css/cart.css) */
        .cart-notice { background: #fdecec; color: #a63d3d; padding: 10px 14px; border-radius: 8px; margin: 0 0 12px; }
        .checkout-form label { display: block; margin: 12px 0 4px; font-weight: 600; }
        .checkout-form input, .checkout-form textarea { width: 100%; box-sizing: border-box; padding: 9px 10px; border: 1px solid #ccc; border-radius: 6px; font: inherit; }
        .checkout-form textarea { min-height: 70px; resize: vertical; }
        .checkout-form .checkout-btn { margin-top: 14px; }
        .cart-summary .muted { opacity: .75; font-size: .9em; }
    </style>
</head>


<body class="cart-page">
    <!-- THANH MÀU XÁM (Đầu trang - Smart Header: Tự ẩn khi lướt xuống, hiện khi lướt lên) -->
    <div class="header" id="mainHeader">
        <div class="header-left">
            <img class="logo" src="../image/kaidologo.jpg">
            <h2>Giỏ hàng của bạn</h2>
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
    <!-- THANH ĐIỀU HƯỚNG MỚI (Nằm dưới thanh màu xám, căn giữa, lướt ngang trên mobile) -->
    <div class="nav-container">
        <div class="nav-scroll-wrapper">
            <div class="nut0"><a href="../index.php" onclick="clickSound()">Trang chủ</a></div>
            <div class="nut1"><a href="../khac/khac.php" onclick="clickSound()">Dự án khác</a></div>
            <div class="nut2"><a href="../hoithem.php" onclick="clickSound()">Trò chuyện</a></div>
            <div class="nut4chon"><a href="../tienich.php" onclick="clickSound()">Tài khoản/Mua hàng</a></div>
            <div class="nut5"><a href="../kythuat/details.php">Chi tiết kỹ thuật</a></div>
            <div class="nut6"><a href="../Blog/blog.php" onclick="clickSound()">Tin tức</a></div>
            <div class="nut3"><a href="../gamemini.php" onclick="clickSound()">Game mini</a></div>
        </div>
    </div>


<!-- CONTENT HEREEEE -->
    <div class="cart-container">
        <div class="cart-header">
            <h1>Giỏ hàng</h1>
            <p>Các sản phẩm bạn đã thêm vào giỏ hàng</p>
        </div>
        <?php foreach ($errors as $err): ?>
            <p class="cart-notice"><?= h($err) ?></p>
        <?php endforeach; ?>
        <?php if (!$cart): ?>
            <div class="cart-card">
                <p>Giỏ hàng đang trống.</p>
                <p><a href="../sanpham/shop.php">Chọn sản phẩm tại KAIDO SHOP</a></p>
            </div>
        <?php else: ?>

            <!-- Hai form rỗng: các nút -, +, Xóa bên dưới gắn vào đây bằng thuộc tính form="..." -->
            <form id="form-update" action="giohang.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(dhToken()) ?>">
                <input type="hidden" name="action" value="update">
            </form>
            <form id="form-remove" action="giohang.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(dhToken()) ?>">
                <input type="hidden" name="action" value="remove">
            </form>

            <!-- Sản phẩm -->
            <div class="cart-card">
                <?php foreach ($sanPham as $sp):
                    $toiDa = min(99, (int)$sp['stock_qty']);
                    $tenAnh = trim((string)($sp['image_path'] ?? ''));
                    $anh = ($tenAnh !== '' && file_exists(__DIR__ . '/' . $THU_MUC_ANH . $tenAnh))
                        ? $THU_MUC_ANH . $tenAnh
                        : '../image/kaidoLogomini.png';
                ?>
                <div class="cart-product">
                    <div class="product-image">
                        <img src="<?= h($anh) ?>" alt="<?= h($sp['product_name']) ?>">
                    </div>
                    <div class="product-info">
                        <h2><?= h($sp['product_name']) ?></h2>
                        <p>Mã sản phẩm: <?= h($sp['sku']) ?></p>
                        <p class="product-price"><?= h(tien($sp['price'])) ?></p>
                    </div>
                    <div class="product-quantity">
                        <button type="submit" form="form-update"
                                name="quantity[<?= (int)$sp['id'] ?>]"
                                value="<?= (int)$sp['so_luong'] - 1 ?>"
                                <?= $sp['so_luong'] <= 1 ? 'disabled' : '' ?>>-</button>
                        <span><?= (int)$sp['so_luong'] ?></span>
                        <button type="submit" form="form-update"
                                name="quantity[<?= (int)$sp['id'] ?>]"
                                value="<?= (int)$sp['so_luong'] + 1 ?>"
                                <?= $sp['so_luong'] >= $toiDa ? 'disabled' : '' ?>>+</button>
                    </div>
                    <div class="product-total">
                        <?= h(tien($sp['price'] * $sp['so_luong'])) ?>
                    </div>
                    <button type="submit" form="form-remove" name="product_id"
                            value="<?= (int)$sp['id'] ?>" class="remove-btn">Xóa</button>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Tổng tiền -->
            <div class="cart-summary">
                <div>
                    <span>Tạm tính</span>
                    <strong><?= h(tien($tong)) ?></strong>
                </div>
                <div>
                    <span>Phí vận chuyển</span>
                    <strong>Chưa tính</strong>
                </div>
                <div class="grand-total">
                    <span>Tổng cộng</span>
                    <strong><?= h(tien($tong)) ?></strong>
                </div>
                <?php if (!$hopLe): ?>
                    <p class="cart-notice">Vui lòng điều chỉnh số lượng cho phù hợp với tồn kho rồi mới đặt hàng.</p>
                <?php elseif (empty($_SESSION['user_id'])): ?>
                    <p>Vui lòng <a href="../Taikhoan/dangnhap.php">đăng nhập</a> trước khi đặt hàng.</p>
                <?php else: ?>
                    <p class="muted">Thanh toán khi nhận hàng (COD).</p>
                    <form class="checkout-form" action="dat_hang.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= h(dhToken()) ?>">
                        <label for="ten">Tên người nhận</label>
                        <input id="ten" name="ten" required maxlength="100"
                               value="<?= h($_SESSION['username'] ?? '') ?>">
                        <label for="sdt">Số điện thoại</label>
                        <input id="sdt" name="sdt" required maxlength="20" pattern="[0-9+ .\-]{9,20}">
                        <label for="dia_chi">Địa chỉ giao hàng</label>
                        <textarea id="dia_chi" name="dia_chi" required maxlength="255"></textarea>
                        <button class="checkout-btn" type="submit">
                            Tiến hành đặt hàng
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="cart-footer">
            <a href="../tienich.php">← Trở về</a>
            <a href="../sanpham/shop.php">KAIDO SHOP</a>
            <a href="../donhang/donhang.php">Xem đơn hàng</a>
        </div>
    </div>

<div id="info">
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
<script src="../js/darklight.js"></script>
<script src="../js/slide.js"></script>
</html>