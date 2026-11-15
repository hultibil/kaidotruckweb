<?php
$glassyDate = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
$glassyDay = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];
$glassyProjects = [
    ['▦', 'Cửa hàng', 'Khám phá linh kiện', 'sanpham/shop.php'],
    ['◇', 'Dự án RC', 'Xem các mẫu xe Kaido', '#xecongnong'],
    ['⚙', 'Kỹ thuật', 'Thông số và cấu hình', 'kythuat/details.php'],
    ['▧', 'Đơn hàng', 'Theo dõi đơn của bạn', 'donhang/donhang.php'],
];
?>
<section class="glassy-overview" aria-labelledby="glassy-title">
    <header class="glassy-overview-heading">
        <div>
            <h1 id="glassy-title">Chào mừng đến Kaido</h1>
            <p><?= $glassyDay[(int) $glassyDate->format('w')] ?> · <?= $glassyDate->format('d/m/Y') ?></p>
        </div>
        <div class="glassy-actions">
            <a href="Giohang/giohang.php">Giỏ hàng</a>
            <a href="sanpham/shop.php">Mua linh kiện ↗</a>
        </div>
    </header>
    <div class="glassy-feature-grid">
        <article class="glassy-feature">
            <div>
                <span class="glassy-feature-tag">RC · DIY · STEM</span>
                <h2>Xe điều khiển<br><em>do Kaido chế tạo</em></h2>
                <p>Khám phá các mẫu xe RC, cấu hình và linh kiện cho dự án của bạn.</p>
                <a href="#xecongnong">Xem dự án ↗</a>
            </div>
            <div class="glassy-orbit" aria-hidden="true">
                <img src="image/hino5001.jpg" alt="">
            </div>
        </article>
        <aside class="glassy-calendar" aria-label="Ngày hôm nay và lối tắt">
            <div class="glassy-date">
                <strong><?= $glassyDate->format('d') ?></strong>
                <span><?= $glassyDay[(int) $glassyDate->format('w')] ?><small>Tháng <?= $glassyDate->format('n') ?>, <?= $glassyDate->format('Y') ?></small></span>
            </div>
            <div class="glassy-week">
                <?php for ($dayOffset = 0; $dayOffset < 7; $dayOffset++): ?>
                    <?php $weekDay = $glassyDate->modify('monday this week')->modify('+' . $dayOffset . ' days'); ?>
                    <span<?= $weekDay->format('Y-m-d') === $glassyDate->format('Y-m-d') ? ' aria-current="date"' : '' ?>>
                        <small><?= $glassyDay[(int) $weekDay->format('w')] === 'Chủ nhật' ? 'CN' : 'T' . ($dayOffset + 2) ?></small><?= $weekDay->format('d') ?>
                    </span>
                <?php endfor; ?>
            </div>
            <a href="Blog/blog.php"><span>▤</span> Tin tức Kaido <span>↗</span></a>
            <a href="sanpham/tuongthich.php"><span>↔</span> Kiểm tra linh kiện <span>↗</span></a>
        </aside>
    </div>
    <div class="glassy-tiles">
        <?php foreach ($glassyProjects as [$projectIcon, $projectTitle, $projectDescription, $projectHref]): ?>
            <a href="<?= $projectHref ?>">
                <span aria-hidden="true"><?= $projectIcon ?></span>
                <strong><?= $projectTitle ?></strong>
                <small><?= $projectDescription ?></small>
            </a>
        <?php endforeach; ?>
    </div>
</section>
