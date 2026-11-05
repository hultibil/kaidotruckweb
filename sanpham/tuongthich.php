<?php
// Đặt tại: sanpham/tuongthich.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../donhang/trangthai.php';

// SỬA: status='active' thay cho <>'hidden'
$products = $conn->query("SELECT id,product_name FROM products WHERE status='active' ORDER BY product_name,id")->fetch_all(MYSQLI_ASSOC);
$a = filter_input(INPUT_GET, 'a', FILTER_VALIDATE_INT) ?: 0;
$b = filter_input(INPUT_GET, 'b', FILTER_VALIDATE_INT) ?: 0;
$chosen = []; $specs = []; $rule = null; $invalid = '';
if ($a > 0 && $b > 0) {
    if ($a === $b) $invalid = 'Vui lòng chọn hai linh kiện khác nhau.';
    else {
        $st = $conn->prepare("SELECT id,product_name FROM products WHERE id IN (?,?) AND status='active'");
        $st->bind_param('ii', $a, $b); $st->execute();
        foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $chosen[(int)$r['id']] = $r['product_name'];
        $st->close();
        if (count($chosen) !== 2) $invalid = 'Không tìm thấy đủ hai sản phẩm.';
        else {
            $lo = min($a, $b); $hi = max($a, $b);
            $st = $conn->prepare('SELECT ket_qua,ghi_chu,updated_at FROM tuong_thich_linh_kien WHERE product_a_id=? AND product_b_id=? LIMIT 1');
            $st->bind_param('ii', $lo, $hi); $st->execute(); $rule = $st->get_result()->fetch_assoc(); $st->close();
            $st = $conn->prepare('SELECT product_id,spec_name,spec_value FROM product_specs WHERE product_id IN (?,?) ORDER BY spec_name');
            $st->bind_param('ii', $a, $b); $st->execute();
            foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $specs[(int)$r['product_id']][] = $r;
            $st->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Kiểm tra tương thích</title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* CSS tuỳ chỉnh đồng bộ giao diện cho trang Kiểm tra tương thích */
        .compat-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 15px;
        }
        .compat-form-box {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .compat-form-box label {
            display: block;
            margin-bottom: 12px;
            font-weight: bold;
            color: #cbd5e1;
        }
        .compat-form-box select {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #444;
            background: #1e293b;
            color: white;
            font-size: 15px;
            margin-top: 5px;
            outline: none;
        }
        .compat-btn {
            background: linear-gradient(to right, #ff416c, #ff4b2b);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            transition: opacity 0.2s;
        }
        .compat-btn:hover {
            opacity: 0.85;
        }
        .compat-result-box {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: center;
        }
        .compat-grid-specs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 25px;
        }
        .spec-card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
        }
        .spec-card h2 {
            font-size: 18px;
            color: #38bdf8;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 8px;
        }
        .compat-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .compat-table th, .compat-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            font-size: 14px;
        }
        .compat-table th {
            color: #94a3b8;
            font-weight: 600;
        }
        .alert-box {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        .alert-bad {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            color: #fca5a5;
        }
        .alert-ok {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid #10b981;
            color: #34d399;
        }
        .alert-warn {
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid #f59e0b;
            color: #fbbf24;
        }
        .muted-text {
            color: #94a3b8;
            font-size: 13px;
        }
        @media (max-width: 768px) {
            .compat-grid-specs {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<!-- HEADER MENU CHUNG -->
<div class="header">
    <img class="logo" src="../image/kaidologo.jpg" alt="Logo">
    <h2>Kiểm tra tương thích giữa các linh kiện</h2>
    <div class="nut0"><a href="../index.php">Trang chủ</a></div>
    <div class="nut1"><a href="../khac/khac.html">Dự án khác</a></div>
    <div class="nut2"><a href="../hoithem.html">Trò chuyện</a></div>
    <div class="nut4chon"><a href="../tienich.php">Tài khoản/Mua hàng</a></div>
    <div class="nut5"><a href="../kythuat/details.html">Chi tiết kỹ thuật</a></div>
    <div class="nut6"><a href="../Blog/blog.html">Tin tức</a></div>
    <div class="nut3"><a href="../gamemini.html">Game mini</a></div>
</div>

<div class="compat-container">
    <h1>KIỂM TRA TƯƠNG THÍCH LINH KIỆN</h1>
    <p class="muted-text" style="text-align: center; margin-bottom: 25px;">Chỉ đưa ra kết luận khi có dữ liệu đã được quản trị viên xác nhận. Thông số sản phẩm chỉ dùng để đối chiếu.</p>

    <!-- FORM LỰA CHỌN -->
    <form method="get" class="compat-form-box">
        <label>Linh kiện thứ nhất:
            <select name="a" required>
                <option value="">-- Chọn sản phẩm --</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $a === (int)$p['id'] ? 'selected' : '' ?>><?= h($p['product_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label style="margin-top: 15px;">Linh kiện thứ hai:
            <select name="b" required>
                <option value="">-- Chọn sản phẩm --</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $b === (int)$p['id'] ? 'selected' : '' ?>><?= h($p['product_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <button class="compat-btn" type="submit">Kiểm tra tương thích</button>
    </form>

    <?php if ($invalid !== ''): ?>
        <div class="alert-box alert-bad"><?= h($invalid) ?></div>
    <?php endif; ?>

    <?php if (count($chosen) === 2 && $invalid === ''): ?>
        <div class="compat-result-box">
            <?php if (!$rule || $rule['ket_qua'] === 'chua_xac_dinh'): ?>
                <h2 class="alert-box alert-warn" style="margin:0;">⚠️ Chưa đủ dữ liệu để xác nhận tương thích</h2>
            <?php elseif ($rule['ket_qua'] === 'tuong_thich'): ?>
                <h2 class="alert-box alert-ok" style="margin:0;">✅ Đã xác nhận tương thích</h2>
            <?php elseif ($rule['ket_qua'] === 'khong_tuong_thich'): ?>
                <h2 class="alert-box alert-bad" style="margin:0;">❌ Đã xác nhận không tương thích</h2>
            <?php else: ?>
                <h2 class="alert-box alert-warn" style="margin:0;">⚠️ Chưa đủ dữ liệu để xác nhận tương thích</h2>
            <?php endif; ?>

            <?php if ($rule && !empty($rule['ghi_chu'])): ?>
                <p style="margin-top: 15px; font-size: 15px; color: #e2e8f0;"><?= nl2br(h($rule['ghi_chu'])) ?></p>
                <p class="muted-text" style="margin-top: 8px;">Cập nhật lần cuối: <?= h($rule['updated_at']) ?></p>
            <?php endif; ?>
        </div>

        <div class="compat-grid-specs">
            <?php foreach ([$a, $b] as $id): ?>
                <section class="spec-card">
                    <h2><?= h($chosen[$id]) ?></h2>
                    <?php if (empty($specs[$id])): ?>
                        <p class="muted-text">Chưa có thông số kỹ thuật.</p>
                    <?php else: ?>
                        <table class="compat-table">
                            <thead>
                                <tr><th>Thông số</th><th>Giá trị</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($specs[$id] as $s): ?>
                                    <tr>
                                        <td><?= h($s['spec_name']) ?></td>
                                        <td><strong><?= h($s['spec_value']) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="quaylai">
    <a href="shop.php">← Quay lại cửa hàng</a>
</div>

<div id="info">
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
</body>
</html>