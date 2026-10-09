<?php
session_start();

// Tạo CAPTCHA phép tính đơn giản cho mỗi lần mở trang
$captcha_a = random_int(1, 9);
$captcha_b = random_int(1, 9);
$_SESSION['captcha_answer'] = $captcha_a + $captcha_b;

// Hiện thông báo nếu CAPTCHA ở lần gửi trước không đúng
$captcha_error = isset($_GET['error']) && $_GET['error'] === 'captcha';
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Đăng ký - Kaido</title>

    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="../css/dndk.css">
</head>

<body>

<div class="auth-box">

    <h2>Đăng ký tài khoản</h2>

    <?php if ($captcha_error): ?>
        <p role="alert">CAPTCHA không đúng hoặc đã hết hạn. Vui lòng thử lại.</p>
    <?php endif; ?>

    <form action="xulydangky.php" method="POST">

        <div class="input-group">

            <label>Tên đăng nhập</label>

            <input
                type="text"
                name="username"
                placeholder="Nhập tên đăng nhập"
                required
            >

        </div>


        <div class="input-group">

            <label>Email</label>

            <input
                type="email"
                name="email"
                placeholder="Nhập email"
                required
            >

        </div>


        <div class="input-group">

            <label>Mật khẩu</label>

            <input
                type="password"
                name="password"
                placeholder="Nhập mật khẩu"
                required
            >

        </div>


        <div class="input-group">

            <label>Nhập lại mật khẩu</label>

            <input
                type="password"
                name="confirm_password"
                placeholder="Nhập lại mật khẩu"
                required
            >

        </div>

        <div class="input-group">
            <label>CAPTCHA: <?= $captcha_a ?> + <?= $captcha_b ?> = ?</label>
            <input
                type="number"
                name="captcha_answer"
                placeholder="Nhập kết quả phép tính"
                required
            >
        </div>

        <button type="submit">
            Đăng ký
        </button>

    </form>


    <p>
        Đã có tài khoản?
        <a href="dangnhap.html">Đăng nhập</a>
    </p>


    <a href="../tienich.html" class="back-home">
        ← Trở về trang chủ
    </a>

</div>

</body>
</html>
