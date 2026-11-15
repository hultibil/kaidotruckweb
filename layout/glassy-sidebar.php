<?php
// Pages supply a relative path so the layout also works below an XAMPP subdirectory.
$glassyRoute = $glassyBase === '../'
    ? basename(dirname($_SERVER['SCRIPT_NAME'])) . '/' . basename($_SERVER['SCRIPT_NAME'])
    : basename($_SERVER['SCRIPT_NAME']);
$glassyAccount = isset($_SESSION['username']) ? 'Taikhoan/taikhoan.php' : 'Taikhoan/dangnhap.php';
$glassyGroups = strpos($glassyRoute, 'admin/') === 0 ? [
    'Quản trị' => [
        ['▦', 'Tổng quan', 'admin/admin.php'],
        ['＋', 'Thêm sản phẩm', 'admin/themsanpham.php'],
        ['▤', 'Danh mục', 'admin/danhmuc.php'],
        ['▣', 'Tồn kho', 'admin/khohang.php'],
        ['↔', 'Tương thích', 'admin/quanly_tuongthich.php'],
        ['▧', 'Đơn hàng', 'admin/capnhat_trangthai.php'],
    ],
    'Kaido' => [['⌂', 'Về trang chủ', 'index.php']],
] : [
    'Khám phá' => [
        ['⌂', 'Tổng quan', 'index.php'],
        ['▦', 'Cửa hàng', 'sanpham/shop.php'],
        ['◇', 'Dự án khác', 'khac/khac.php'],
        ['⚙', 'Chi tiết kỹ thuật', 'kythuat/details.php'],
        ['▤', 'Tin tức', 'Blog/blog.php'],
    ],
    'Tiện ích' => [
        ['♡', 'Yêu thích', 'sanpham/yeuthich.php'],
        ['▣', 'Giỏ hàng', 'Giohang/giohang.php'],
        ['▧', 'Đơn hàng', 'donhang/donhang.php'],
        ['↔', 'Tương thích', 'sanpham/tuongthich.php'],
        ['☏', 'Trò chuyện', 'hoithem.php'],
        ['✦', 'Game mini', 'gamemini.php'],
    ],
];
?>
<a class="glassy-skip" href="#glassy-content">Đến nội dung</a>
<aside class="glassy-sidebar" aria-label="Điều hướng Kaido">
    <a class="glassy-brand" href="<?= $glassyBase ?>index.php">
        <img src="<?= $glassyBase ?>image/kaidoLogomini.png" alt="">
        <span><strong>Kaido</strong><small>RC · DIY · STEM</small></span>
    </a>
    <button type="button" class="glassy-theme-toggle">Chuyển giao diện</button>
    <details class="glassy-menu" open>
        <summary>Điều hướng</summary>
        <nav aria-label="Menu chính">
            <?php foreach ($glassyGroups as $glassyLabel => $glassyLinks): ?>
                <div class="glassy-section-label"><?= $glassyLabel ?></div>
                <?php foreach ($glassyLinks as [$glassyIcon, $glassyText, $glassyHref]): ?>
                    <?php
                    $glassyActive = $glassyRoute === $glassyHref
                        || ($glassyHref === 'sanpham/shop.php' && $glassyRoute === 'sanpham/chitietsanpham.php')
                        || ($glassyHref === 'donhang/donhang.php' && in_array($glassyRoute, ['donhang/lichsu.php', 'donhang/theodoi.php'], true));
                    ?>
                    <a class="glassy-nav-link" href="<?= $glassyBase . $glassyHref ?>"<?= $glassyActive ? ' aria-current="page"' : '' ?>>
                        <span aria-hidden="true"><?= $glassyIcon ?></span><?= $glassyText ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
    </details>
    <a class="glassy-profile" href="<?= $glassyBase . $glassyAccount ?>">
        <span aria-hidden="true">◉</span>
        <span><strong><?= isset($_SESSION['username']) ? htmlspecialchars((string) $_SESSION['username'], ENT_QUOTES, 'UTF-8') : 'Đăng nhập' ?></strong><small>Tài khoản</small></span>
    </a>
</aside>
