<?php
require_once __DIR__ . '/baove.php';   // PHẢI nằm đầu tiên: chặn người chưa đăng nhập / không phải admin

/* ------------------------------------------------------------------
   XỬ LÝ XÓA sản phẩm (chỉ nhận POST + CSRF, không dùng link GET nữa)
------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    admin_csrf_check();
    $product_id = (int)($_POST['id'] ?? 0);
    try {
        $stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $stmt->close();
        header('Location: admin.php?tab=products&msg=deleted');
    } catch (mysqli_sql_exception $e) {
        // Thường do sản phẩm còn được bảng khác tham chiếu (khóa ngoại)
        header('Location: admin.php?tab=products&msg=delete_failed');
    }
    exit;
}

/* ------------------------------------------------------------------
   XỬ LÝ CẬP NHẬT giá / tồn kho
------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    admin_csrf_check();
    $id        = (int)($_POST['id'] ?? 0);
    $price     = (float)($_POST['price'] ?? 0);
    $stock_qty = (int)($_POST['stock_qty'] ?? 0);

    if ($id <= 0 || $price < 0 || $stock_qty < 0) {
        header('Location: admin.php?tab=products&msg=invalid');
        exit;
    }
    $stmt = $conn->prepare('UPDATE products SET price = ?, stock_qty = ?, updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('dii', $price, $stock_qty, $id);
    $stmt->execute();
    $stmt->close();

    header('Location: admin.php?tab=products&msg=updated');
    exit;
}

$tab = (isset($_GET['tab']) && $_GET['tab'] === 'users') ? 'users' : 'products';

$thong_bao = [
    'deleted'       => ['ok',  'Đã xóa sản phẩm thành công!'],
    'updated'       => ['ok',  'Đã cập nhật thông tin sản phẩm thành công!'],
    'added'         => ['ok',  'Đã thêm sản phẩm mới thành công!'],
    'delete_failed' => ['err', 'Không xóa được sản phẩm (có thể đang được đơn hàng sử dụng).'],
    'invalid'       => ['err', 'Dữ liệu không hợp lệ (giá và tồn kho phải là số không âm).'],
];
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang Quản Trị - Kaido Shop</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-container { width: 95%; margin: 20px auto; background: rgba(0,0,0,0.4); padding: 25px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        .admin-tabs { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
        .admin-tabs a { padding: 10px 20px; background: #222; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; transition: 0.3s; }
        .admin-tabs a.active, .admin-tabs a:hover { background: linear-gradient(to right, #ff416c, #ff4b2b); }
        table.admin-table { width: 100%; border-collapse: collapse; margin-top: 15px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden; }
        table.admin-table th, table.admin-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        table.admin-table th { background: rgba(0,0,0,0.6); color: silver; }
        table.admin-table tr:hover { background: rgba(255,255,255,0.05); }
        .btn-action { padding: 6px 12px; border-radius: 5px; text-decoration: none; color: white; font-size: 14px; margin-right: 5px; border: none; cursor: pointer; }
        .btn-delete { background: #ff416c; }
        .btn-save { background: #28a745; }
        .btn-action:hover { opacity: 0.8; }
        .form-edit input { padding: 6px; border-radius: 5px; border: 1px solid #444; background: #222; color: white; width: 110px; }
        .alert-success { background: rgba(40,167,69,0.2); border: 1px solid #28a745; padding: 10px; border-radius: 8px; margin-bottom: 15px; color: #d4edda; }
        .alert-error { background: rgba(255,65,108,0.2); border: 1px solid #ff416c; padding: 10px; border-radius: 8px; margin-bottom: 15px; color: #ff8fa3; }
        .inline-form { display: inline; }
        .topbar { text-align: right; margin-bottom: 10px; }
        .topbar a { color: silver; margin-left: 12px; }
    </style>
</head>
<body>

<div class="admin-container">
    <h1 class="silver" style="margin-top:0; text-align:center;">TRANG QUẢN TRỊ HỆ THỐNG</h1>

    <div class="topbar">
        Xin chào, <strong><?= h($admin_username) ?></strong> 👋
        <a href="../index.php">Trang chủ</a>
        <a href="../Taikhoan/dangxuat.php">Đăng xuất</a>
    </div>

    <div class="admin-tabs">
        <a href="admin.php?tab=products" class="<?= $tab === 'products' ? 'active' : '' ?>">Quản lý Sản phẩm</a>
        <a href="admin.php?tab=users" class="<?= $tab === 'users' ? 'active' : '' ?>">Quản lý Người dùng</a>
    </div>

    <?php if (isset($thong_bao[$msg])): ?>
        <div class="<?= $thong_bao[$msg][0] === 'ok' ? 'alert-success' : 'alert-error' ?>">
            <?= h($thong_bao[$msg][1]) ?>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'products'): ?>
        <h2 class="text">Danh sách sản phẩm hiện có</h2>
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Tên sản phẩm</th><th>SKU</th><th>Giá (VNĐ)</th>
                        <th>Tồn kho</th><th>Trạng thái</th><th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $result = $conn->query('SELECT id, product_name, sku, price, stock_qty, status FROM products ORDER BY id DESC');
                if ($result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                        $fid = 'f' . (int)$row['id'];
                ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= h($row['product_name']) ?></td>
                        <td><?= h($row['sku']) ?></td>
                        <td class="form-edit"><input form="<?= $fid ?>" type="number" min="0" step="1" name="price" value="<?= h($row['price']) ?>"></td>
                        <td class="form-edit"><input form="<?= $fid ?>" type="number" min="0" step="1" name="stock_qty" value="<?= (int)$row['stock_qty'] ?>"></td>
                        <td><?= h($row['status']) ?></td>
                        <td>
                            <!-- Form lưu (các ô nhập ở trên trỏ vào form này bằng thuộc tính form="...") -->
                            <form id="<?= $fid ?>" class="inline-form" action="admin.php?tab=products" method="POST">
                                <?= admin_csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" name="update_product" class="btn-action btn-save">Lưu</button>
                            </form>
                            <!-- Xóa bằng POST + CSRF -->
                            <form class="inline-form" action="admin.php?tab=products" method="POST"
                                  onsubmit="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này không?');">
                                <?= admin_csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" name="delete_product" class="btn-action btn-delete">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php
                    endwhile;
                else:
                    echo "<tr><td colspan='7' style='text-align:center;'>Chưa có sản phẩm nào trong cơ sở dữ liệu.</td></tr>";
                endif;
                ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:30px; text-align:center;">
            <a href="themsanpham.php" class="btn-primary">Thêm sản phẩm</a>
        </div>

    <?php else: ?>
        <h2 class="text">Danh sách tài khoản người dùng</h2>
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Tên đăng nhập</th><th>Email</th><th>Họ và tên</th>
                        <th>Số điện thoại</th><th>Vai trò</th><th>Ngày tạo</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $result = $conn->query('SELECT id, username, email, full_name, phone, role, created_at FROM users ORDER BY id DESC');
                if ($result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= h($row['username']) ?></td>
                        <td><?= h($row['email']) ?></td>
                        <td><?= h($row['full_name']) ?></td>
                        <td><?= h($row['phone']) ?></td>
                        <td><span style="padding:3px 8px; border-radius:4px; background:<?= $row['role'] === 'admin' ? '#ff416c' : '#0072ff' ?>;"><?= h($row['role']) ?></span></td>
                        <td><?= h($row['created_at']) ?></td>
                    </tr>
                <?php
                    endwhile;
                else:
                    echo "<tr><td colspan='7' style='text-align:center;'>Chưa có tài khoản người dùng nào.</td></tr>";
                endif;
                ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
