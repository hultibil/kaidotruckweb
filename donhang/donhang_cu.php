<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/trangthai.php';
$userId = dhRequireLogin();

// Thư mục chứa ảnh sản phẩm (giống shop.php)
$THU_MUC_ANH = '../image/uploads/';

// Danh sách đơn của tài khoản đang đăng nhập, mới nhất trước
$stmt = $conn->prepare(
    'SELECT id, created_at, total_amount, status
     FROM orders WHERE user_id=? ORDER BY created_at DESC, id DESC'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$donHang = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Sản phẩm của tất cả các đơn đó, lấy một lần rồi nhóm theo đơn
$spTheoDon = [];
if ($donHang) {
    $stmt = $conn->prepare(
        'SELECT oi.order_id, oi.product_id, oi.product_name, oi.quantity, oi.unit_price, p.image_path
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE o.user_id=?
         ORDER BY oi.order_id, oi.id'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $it) {
        $spTheoDon[(int)$it['order_id']][] = $it;
    }
}

// [ĐÁNH GIÁ] Các sản phẩm mà tài khoản này đã đánh giá (để chỉ nhắc những sản phẩm chưa đánh giá)
$daDanhGia = [];
try {
    $stmt = $conn->prepare('SELECT product_id FROM product_reviews WHERE user_id=?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $daDanhGia[(int)$r['product_id']] = true;
    }
    $stmt->close();
} catch (Throwable $e) {
    error_log('donhang/danhgia: ' . $e->getMessage()); // chưa chạy danhgia.sql thì chỉ bỏ qua phần nhắc
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng - Kaido</title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="../css/cart.css">
<link rel="stylesheet" href="../css/glassy.css">
<script src="../js/glassy-theme.js"></script>
<style>
    /* [ĐÁNH GIÁ] Khung nhắc đánh giá sản phẩm khi đơn đã giao thành công */
    .dg-nhac{margin:12px 0;padding:12px 14px;border-radius:14px;border:1px dashed var(--field-border,#8eb5db);background:rgba(245,158,11,.10);text-align:left}
    .dg-nhac-tieude{font-weight:700;margin-bottom:8px}
    .dg-nhac-ds{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:6px}
    html body.glassy-page .dg-nhac a.dg-nut{display:inline-block;padding:7px 14px;border-radius:99px;font-size:13px;font-weight:700;text-decoration:none;color:#fff!important;background:var(--primary-gradient,#0861c3)!important}
    html body.glassy-page .dg-nhac a.dg-lienket{color:var(--accent)!important;font-weight:700}
</style>
</head>

<body class="glassy-page cart-page">
<?php $glassyBase = '../'; require __DIR__ . '/../layout/glassy-sidebar.php'; ?>
<div id="glassy-content" class="glassy-content" tabindex="-1">
    

    <!-- THANH MÀU XÁM (Đầu trang - Smart Header: Tự ẩn khi lướt xuống, hiện khi lướt lên) -->
    <div class="header" id="mainHeader">
        <div class="header-left">
            <img class="logo" src="../image/kaidologo.jpg">
            <h2>Đơn hàng</h2>
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



    <div class="cart-container">

        <div class="cart-header">
            <h1>Đơn hàng của tôi</h1>
            <p>Theo dõi các đơn hàng đã đặt</p>
        </div>

        <?php if (!$donHang): ?>

            <div class="cart-card">
                <p>Bạn chưa có đơn hàng nào.</p>
                <p><a href="../sanpham/shop.php">Mua sắm tại KAIDO SHOP</a></p>
            </div>

        <?php else: ?>

            <?php foreach ($donHang as $d):
                $dsSP = $spTheoDon[(int)$d['id']] ?? [];
                $dau = $dsSP[0] ?? null;
                $conLai = max(0, count($dsSP) - 1);
                $tongSL = 0;
                foreach ($dsSP as $x) $tongSL += (int)$x['quantity'];

                // [ĐÁNH GIÁ] sản phẩm trong đơn đã giao mà chưa đánh giá
                $chuaDG = [];
                if ($d['status'] === 'completed') {
                    foreach ($dsSP as $x) {
                        $pid = (int)$x['product_id'];
                        if ($pid > 0 && empty($daDanhGia[$pid]) && !isset($chuaDG[$pid])) {
                            $chuaDG[$pid] = $x['product_name'];
                        }
                    }
                }

                $tenAnh = $dau ? trim((string)($dau['image_path'] ?? '')) : '';
                $anh = ($tenAnh !== '' && file_exists(__DIR__ . '/' . $THU_MUC_ANH . $tenAnh))
                    ? $THU_MUC_ANH . $tenAnh
                    : '../image/kaidoLogomini.png';
            ?>
            <!-- Đơn hàng <?= h(maDon((int)$d['id'])) ?> -->
            <div class="order-card">

                <div class="order-header">
                    <div>
                        <strong>Đơn hàng #<?= h(maDon((int)$d['id'])) ?></strong>
                        <p>Ngày đặt: <?= h(date('d/m/Y H:i', strtotime($d['created_at']))) ?></p>
                    </div>

                    <span class="order-status" style="background:<?= h(mauTrangThai($d['status'])) ?>;color:#fff">
                        <?= h(tenTrangThai($d['status'])) ?>
                    </span>
                </div>

                <?php if ($dau): ?>
                <div class="order-product">
                    <div class="product-image">
                        <img src="<?= h($anh) ?>" alt="<?= h($dau['product_name']) ?>">
                    </div>

                    <div class="product-info">
                        <h2><?= h($dau['product_name']) ?></h2>
                        <p>Số lượng: <?= (int)$dau['quantity'] ?></p>
                        <p><?= h(tien($dau['unit_price'] * $dau['quantity'])) ?></p>
                        <?php if ($conLai > 0): ?>
                            <p>và <?= $conLai ?> sản phẩm khác (tổng <?= $tongSL ?> sản phẩm)</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($d['status'] === 'completed'): ?>
                <div class="dg-nhac">
                    <?php if ($chuaDG): ?>
                        <div class="dg-nhac-tieude">⭐ Đơn hàng đã giao thành công! Bạn thấy sản phẩm thế nào?</div>
                        <div class="dg-nhac-ds">
                            <?php foreach ($chuaDG as $pid => $ten): ?>
                                <a class="dg-nut" href="../sanpham/danhgia.php?order_id=<?= (int)$d['id'] ?>&amp;product_id=<?= (int)$pid ?>">Đánh giá: <?= h(mb_strimwidth($ten, 0, 40, '…', 'UTF-8')) ?></a>
                            <?php endforeach; ?>
                        </div>
                        <small>Bạn có thể bỏ qua, đánh giá sau tại mục "Đánh giá của tôi".</small>
                    <?php else: ?>
                        ✓ Bạn đã đánh giá các sản phẩm trong đơn này. <a class="dg-lienket" href="../sanpham/danhgia_cho.php">Xem đánh giá của tôi</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="order-bottom">
                    <span>Tổng tiền</span>
                    <strong><?= h(tien($d['total_amount'])) ?></strong>
                </div>

                <form action="theodoi.php" method="get">
                    <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                    <button class="detail-btn" type="submit">
                        Xem chi tiết
                    </button>
                </form>

            </div>
            <?php endforeach; ?>

        <?php endif; ?>

        <div class="cart-footer">
            <a href="../tienich.php">← Trở về</a>
            <a href="../sanpham/shop.php">KAIDO SHOP</a>
            <a href="../Giohang/giohang.php">Giỏ hàng →</a>
        </div>
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


</div><!-- glassy-content -->
</body>
<script src="../js/slide.js"></script>
</html>
