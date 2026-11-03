
<?php

// 1. Khai báo dữ liệu sản phẩm
$product = [
    [
        'name' => 'Máy tính DELL',
        'description' => 'Máy tính mạnh mẽ, hiệu năng cao, thiết kế đẹp',
        'price' => 25000000,
        'stock' => 10
    ],
    [
        'name' => 'Máy tính HP',
        'description' => 'Máy tính ổn định, độ bền cao, hỗ trợ tốt',
        'price' => 20000000,
        'stock' => 5
    ],
    [
        'name' => 'Máy tính Lenovo',
        'description' => 'Máy tính nhẹ, di động, phù hợp với nhu cầu sử dụng hàng ngày',
        'price' => 15000000,
        'stock' => 8
    ],
    [
        'name' => 'Máy tính ASUS',
        'description' => 'Máy tính đa năng, hiệu suất tốt, giá cả hợp lý',
        'price' => 18000000,
        'stock' => 10
    ]
];

// 2. Khai báo hàm phân loại giá
function classifyPrice($price) {
    if ($price < 16000000) {
        return "Giá thấp";
    } elseif ($price < 20000000) {
        return "Giá trung bình";
    } else {
        return "Giá cao";
    }
}

// 3. Khai báo hàm định dạng tiền
function formatPrice($price) {
    return number_format($price, 0, ',', '.') . " VNĐ";
}

?>

<style>
    .product_card {
        background-color: #ffffff;
        color: #222;
        border: 1px solid #ccc;
        padding: 10px;
        margin-bottom: 10px;
    }
</style>

<?php foreach ($product as $item): ?>

    <div class="product_card">

        <h2><?php echo $item['name']; ?></h2>

        <p><?php echo $item['description']; ?></p>

        <p>Giá: <?php echo formatPrice($item['price']); ?></p>

        <p>Số lượng tồn kho: <?php echo $item['stock']; ?></p>

        <p>Phân loại: <?php echo classifyPrice($item['price']); ?></p>

        <?php if ($item['stock'] > 0): ?>
            <p style="color: green;">Còn hàng</p>
        <?php else: ?>
            <p style="color: red;">Hết hàng</p>
        <?php endif; ?>

    </div>

<?php endforeach; ?>

<?php
phpinfo();
?>