<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng Kaido</title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="../css/cart.css">
</head>

<body class="cart-page">
    <div class="header"> <!--Add CSS-->
            <img class="logo" src="../image/kaidologo.jpg">
            <h2>Giỏ hàng của bạn</h2>
            <div class="nut0">
                <a href="../index.php" onclick="clickSound()">Trang chủ</a>
            </div>
            <div class="nut1"> <!--Add CSS-->
                <a href="../khac/khac.html" onclick="clickSound()">Dự án khác</a>
            </div>
            <div class="nut2">
                <a href="../hoithem.html" onclick="clickSound()">Trò chuyện</a>
            </div>
            <div class="nut4chon">
                <a href="../tienich.html" onclick="clickSound()">Tài khoản/Mua hàng</a>
            </div>
            <div class="nut5">
                <a href="../kythuat/details.html">Chi tiết kỹ thuật</a>
            </div>
            <div class="nut6">
                <a href="../Blog/blog.html" onclick="clickSound()">Tin tức</a>
            </div>
            <div class="nut3">
                <a href="../gamemini.html" onclick="clickSound()">Game mini</a>
            </div>
    </div>
    <div class="cart-container">

        <div class="cart-header">
            <h1>Giỏ hàng</h1>
            <p>Các sản phẩm bạn đã thêm vào giỏ hàng</p>
        </div>

        <!-- Sản phẩm -->
        <div class="cart-card">

            <div class="cart-product">

                <div class="product-image">
                    <img src="images/sanpham1.jpg" alt="Sản phẩm">
                </div>

                <div class="product-info">
                    <h2>Mạch sạc TP4056</h2>
                    <p>Mã sản phẩm: KD001</p>
                    <p class="product-price">30.000 VNĐ</p>
                </div>

                <div class="product-quantity">
                    <button>-</button>
                    <span>3</span>
                    <button>+</button>
                </div>

                <div class="product-total">
                    90.000 VNĐ
                </div>

                <button class="remove-btn">Xóa</button>

            </div>

            <div class="cart-product">

                <div class="product-image">
                    <img src="images/sanpham2.jpg" alt="Sản phẩm">
                </div>

                <div class="product-info">
                    <h2>Xe RC Kaido Off-road</h2>
                    <p>Mã sản phẩm: KD002</p>
                    <p class="product-price">550.000 VNĐ</p>
                </div>

                <div class="product-quantity">
                    <button>-</button>
                    <span>2</span>
                    <button>+</button>
                </div>

                <div class="product-total">
                    1.100.000 VNĐ
                </div>

                <button class="remove-btn">Xóa</button>

            </div>

        </div>

        <!-- Tổng tiền -->
        <div class="cart-summary">

            <div>
                <span>Tạm tính</span>
                <strong>1.450.000 VNĐ</strong>
            </div>

            <div>
                <span>Phí vận chuyển</span>
                <strong>30.000 VNĐ</strong>
            </div>

            <div class="grand-total">
                <span>Tổng cộng</span>
                <strong>1.480.000 VNĐ</strong>
            </div>

            <button class="checkout-btn">
                Tiến hành đặt hàng
            </button>

        </div>

        <div class="cart-footer">
            <a href="../tienich.html">← Trở về</a>
            <a href="../sanpham/shop.php">KAIDO SHOP</a>
            <a href="../donhang/donhang.php">Xem đơn hàng</a>
        </div>

    </div>

</body>
</html>
