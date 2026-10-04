
<?php

session_start();

require_once "db.php";


// Nếu chưa đăng nhập
if (!isset($_SESSION['user_id'])) {
    ?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Chưa đăng nhập - Kaido</title>

    <link rel="stylesheet" href="css/dndk.css">
</head>

<body>

<div class="auth-box">

    <h2>Bạn chưa đăng nhập</h2>

    <p>
        Vui lòng đăng nhập để xem thông tin tài khoản của bạn.
    </p>

    <div class="account-actions">

        <!-- Nút này bạn tự đổi liên kết -->
        <a href="tienich.html">
            Trở về
        </a>

        <!-- Dẫn tới trang đăng nhập -->
        <a href="dangnhap.html">
            Đăng nhập
        </a>

    </div>

</div>

</body>
</html>

<?php
    exit;
}


// Lấy thông tin tài khoản
$user_id = $_SESSION['user_id'];

$sql = "SELECT * FROM users WHERE id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();


// Nếu không tìm thấy tài khoản
if (!$user) {
    die("Không tìm thấy tài khoản.");
}

?>
<!DOCTYPE html>

<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Tài khoản của bạn</title>

    <link
        rel="stylesheet"
        href="css/dndk.css"
    >

</head>


<body class="account-page">


    <!-- ==================== HEADER ==================== -->

    <div class="header">

        <img
            class="logo"
            src="image/kaidologo.jpg"
            alt="Kaido"
        >

        <h2>
            Thông tin tài khoản của bạn
        </h2>


        <div class="nut0">
            <a href="index.php">
                Trang chủ
            </a>
        </div>


        <div class="nut1">
            <a href="khac.html">
                Dự án khác
            </a>
        </div>


        <div class="nut2">
            <a href="hoithem.html">
                Trò chuyện
            </a>
        </div>


        <div class="nut4chon">
            <a href="tienich.html">
                Tài khoản/Mua hàng
            </a>
        </div>


        <div class="nut5">
            <a href="details.html">
                Chi tiết kỹ thuật
            </a>
        </div>


        <div class="nut6">
            <a href="blog.html">
                Tin tức
            </a>
        </div>


        <div class="nut3">
            <a href="gamemini.html">
                Game mini
            </a>
        </div>

    </div>



    <!-- ==================== ACCOUNT ==================== -->

    <div class="account-container">


        <div class="account-header">

            <h1>
                Tài khoản của bạn
            </h1>

            <p>
                Quản lý thông tin tài khoản Kaido
            </p>

        </div>



        <!-- ==================== THÔNG TIN ==================== -->

        <div class="account-card">

            <h2>
                Thông tin tài khoản
            </h2>


            <div class="account-info">


                <div class="info-row">

                    <span>
                        Tên đăng nhập
                    </span>

                    <strong>
                        <?php echo htmlspecialchars($user['username']); ?>
                    </strong>

                </div>


                <div class="info-row">

                    <span>
                        Email
                    </span>

                    <strong>
                        <?php echo htmlspecialchars($user['email']); ?>
                    </strong>

                </div>


                <div class="info-row">

                    <span>
                        Vai trò
                    </span>

                    <strong>
                        <?php echo htmlspecialchars($user['role']); ?>
                    </strong>

                </div>


                <div class="info-row">

                    <span>
                        Ngày tham gia
                    </span>

                    <strong>
                        <?php echo date("d/m/Y", strtotime($user['created_at'])); ?>
                    </strong>

                </div>


            </div>

        </div>



        <!-- ==================== CHỨC NĂNG ==================== -->

        <div class="account-card">

            <h2>
                Chức năng tài khoản
            </h2>


            <div class="account-actions">

                <button>
                    Chỉnh sửa thông tin
                </button>

                <button>
                    Đơn hàng
                </button>

                <button>
                    Giỏ hàng
                </button>

                <button>
                    Đổi mật khẩu
                </button>

            </div>

        </div>



        <!-- ==================== ĐIỀU HƯỚNG ==================== -->

        <div class="account-footer">

            <a href="tienich.html">
                ← Trở về
            </a>


            <a href="dangxuat.php">
                Đăng xuất
            </a>

        </div>


    </div>

</body>

</html>
