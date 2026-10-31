<?php
require_once __DIR__ . '/baove.php';   // PHẢI nằm đầu tiên

$error = '';
$old = ['product_name' => '', 'category_id' => '', 'sku' => '', 'price' => '0', 'stock_qty' => '10', 'status' => 'active', 'description' => ''];

/** Lưu ảnh: kiểm tra đây đúng là ảnh thật, đổi tên ngẫu nhiên, lưu thẳng vào thư mục image/ (chỉ lưu TÊN FILE vào DB) */
function luu_anh_san_pham(array $f): ?string
{
    if (!isset($f['error']) || $f['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Tải ảnh lên thất bại (mã lỗi ' . (int)$f['error'] . ').');
    }
    if ($f['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Ảnh tối đa 2MB.');
    }
    $info = @getimagesize($f['tmp_name']);   // false nếu không phải ảnh thật
    $cho_phep = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($cho_phep[$info[2]])) {
        throw new RuntimeException('Chỉ nhận ảnh JPG, PNG, GIF hoặc WEBP hợp lệ.');
    }
    $ten = 'sp_' . bin2hex(random_bytes(8)) . '.' . $cho_phep[$info[2]];   // đuôi do SERVER quyết định
    $thu_muc = __DIR__ . '/../image/';
    if (!is_dir($thu_muc) || !move_uploaded_file($f['tmp_name'], $thu_muc . $ten)) {
        throw new RuntimeException('Không lưu được ảnh. Kiểm tra thư mục image có tồn tại và có quyền ghi.');
    }
    return $ten;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();

    $old['product_name'] = trim($_POST['product_name'] ?? '');
    $old['category_id']  = (int)($_POST['category_id'] ?? 0);
    $old['sku']          = trim($_POST['sku'] ?? '');
    $old['price']        = $_POST['price'] ?? '0';
    $old['stock_qty']    = $_POST['stock_qty'] ?? '0';
    $old['status']       = $_POST['status'] ?? 'active';
    $old['description']  = trim($_POST['description'] ?? '');

    $price = (float)$old['price'];
    $stock = (int)$old['stock_qty'];

    if ($old['product_name'] === '' || $old['category_id'] <= 0 || $old['sku'] === '') {
        $error = 'Vui lòng điền đầy đủ Tên sản phẩm, Danh mục và SKU!';
    } elseif ($price < 0 || $stock < 0) {
        $error = 'Giá và số lượng tồn kho không được âm.';
    } elseif (!in_array($old['status'], ['active', 'out_of_stock', 'hidden'], true)) {
        $error = 'Trạng thái không hợp lệ.';
    } else {
        try {
            $image_path = luu_anh_san_pham($_FILES['image_file'] ?? []);   // null nếu không chọn ảnh

            $stmt = $conn->prepare(
                'INSERT INTO products (category_id, sku, product_name, description, price, stock_qty, image_path, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->bind_param(
                'isssdiss',
                $old['category_id'], $old['sku'], $old['product_name'], $old['description'],
                $price, $stock, $image_path, $old['status']
            );
            $stmt->execute();
            $stmt->close();

            header('Location: admin.php?tab=products&msg=added');
            exit;
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                $error = 'SKU "' . $old['sku'] . '" đã tồn tại, hãy dùng mã khác.';
            } elseif ($e->getCode() === 1452) {
                $error = 'Danh mục không tồn tại.';
            } else {
                $error = 'Lỗi khi thêm vào cơ sở dữ liệu.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Sản Phẩm Mới</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .form-container { width: 700px; max-width: 90%; margin: 30px auto; background: rgba(0,0,0,0.5); padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: silver; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #444; background: #222; color: white; font-size: 16px; box-sizing: border-box; }
        .form-group textarea { height: 100px; resize: vertical; }
        .btn-submit { background: linear-gradient(to right, #28a745, #20c997); color: white; padding: 12px 25px; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; font-weight: bold; }
        .btn-submit:hover { opacity: 0.8; }
        .alert-error { background: rgba(255,65,108,0.2); border: 1px solid #ff416c; padding: 10px; border-radius: 8px; margin-bottom: 15px; color: #ff8fa3; }
        .hint { font-size: 13px; color: #aaa; margin-top: 4px; }
    </style>
</head>
<body>

<div class="form-container">
    <h1 class="silver" style="margin-top:0; text-align:center; font-size:28px;">THÊM SẢN PHẨM MỚI</h1>

    <?php if ($error !== ''): ?>
        <div class="alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form action="themsanpham.php" method="POST" enctype="multipart/form-data">
        <?= admin_csrf_field() ?>

        <div class="form-group">
            <label>Tên sản phẩm (*):</label>
            <input type="text" name="product_name" required maxlength="255" value="<?= h($old['product_name']) ?>" placeholder="Nhập tên sản phẩm...">
        </div>

        <div class="form-group">
            <label>Danh mục sản phẩm (*):</label>
            <select name="category_id" required>
                <option value="">-- Chọn danh mục --</option>
                <?php
                $cat_result = $conn->query('SELECT id, name FROM categories ORDER BY id');
                while ($cat = $cat_result->fetch_assoc()) {
                    $sel = ((int)$old['category_id'] === (int)$cat['id']) ? ' selected' : '';
                    echo "<option value='" . (int)$cat['id'] . "'$sel>" . h($cat['name']) . '</option>';
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label>Mã hàng kinh doanh (SKU) (*):</label>
            <input type="text" name="sku" required maxlength="50" value="<?= h($old['sku']) ?>" placeholder="KD011">
        </div>

        <div style="display:flex; gap:15px;">
            <div class="form-group" style="flex:1;">
                <label>Giá bán (VNĐ):</label>
                <input type="number" min="0" step="1" name="price" value="<?= h($old['price']) ?>" required>
            </div>
            <div class="form-group" style="flex:1;">
                <label>Số lượng tồn kho:</label>
                <input type="number" min="0" step="1" name="stock_qty" value="<?= h($old['stock_qty']) ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Trạng thái sản phẩm:</label>
            <select name="status">
                <option value="active"       <?= $old['status'] === 'active' ? 'selected' : '' ?>>active - Đang bán</option>
                <option value="out_of_stock" <?= $old['status'] === 'out_of_stock' ? 'selected' : '' ?>>out_of_stock - Hết hàng</option>
                <option value="hidden"       <?= $old['status'] === 'hidden' ? 'selected' : '' ?>>hidden - Ẩn khỏi cửa hàng</option>
            </select>
        </div>

        <div class="form-group">
            <label>Hình ảnh sản phẩm:</label>
            <input type="file" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp">
            <div class="hint">Không bắt buộc. JPG/PNG/GIF/WEBP, tối đa 2MB. Không chọn ảnh thì cửa hàng hiện "Chưa có ảnh minh họa".</div>
        </div>

        <div class="form-group">
            <label>Mô tả sản phẩm:</label>
            <textarea name="description" placeholder="Nhập thông tin mô tả chi tiết sản phẩm..."><?= h($old['description']) ?></textarea>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:25px;">
            <a href="admin.php?tab=products" style="color:silver; text-decoration:none;">&larr; Hủy, quay lại quản lý</a>
            <button type="submit" class="btn-submit">Thêm sản phẩm</button>
        </div>
    </form>
</div>

</body>
</html>
