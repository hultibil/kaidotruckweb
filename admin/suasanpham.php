<?php
require_once __DIR__ . '/baove.php';
require_once __DIR__ . '/anh.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Mã sản phẩm không hợp lệ.');
}
$stmt = $conn->prepare('SELECT id, category_id, sku, product_name, description, price, stock_qty, image_path, status FROM products WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$product) {
    http_response_code(404);
    exit('Không tìm thấy sản phẩm.');
}
$error = '';
$data = $product;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $data['product_name'] = trim((string)($_POST['product_name'] ?? ''));
    $data['sku'] = trim((string)($_POST['sku'] ?? ''));
    $data['category_id'] = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $data['description'] = trim((string)($_POST['description'] ?? ''));
    $data['status'] = (string)($_POST['status'] ?? 'active');
    $priceInput = trim((string)($_POST['price'] ?? ''));
    $stockInput = trim((string)($_POST['stock_qty'] ?? ''));
    $data['price'] = $priceInput;
    $data['stock_qty'] = $stockInput;

    $priceValid = (bool)preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $priceInput);
    $stock = filter_var($stockInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($data['product_name'] === '' || (function_exists('mb_strlen') ? mb_strlen($data['product_name'], 'UTF-8') : strlen($data['product_name'])) > 255 || $data['sku'] === '' || strlen($data['sku']) > 50 || !$data['category_id']) {
        $error = 'Nhập tên, danh mục và SKU hợp lệ.';
    } elseif (!$priceValid || $stock === false) {
        $error = 'Giá và số lượng phải là số không âm hợp lệ.';
    } elseif (!in_array($data['status'], ['active', 'inactive'], true)) {   // khớp enum products.status
        $error = 'Trạng thái sản phẩm không hợp lệ.';
    } else {
        $newImage = null;
        try {
            $newImage = admin_luu_anh($_FILES['image_file'] ?? []);
            $image = $newImage ?? $product['image_path'];
            $price = (float)$priceInput;
            $category = (int)$data['category_id'];
            $stmt = $conn->prepare('UPDATE products SET category_id = ?, sku = ?, product_name = ?, description = ?, price = ?, stock_qty = ?, image_path = ?, status = ?, updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('isssdissi', $category, $data['sku'], $data['product_name'], $data['description'], $price, $stock, $image, $data['status'], $id);
            $stmt->execute();
            $stmt->close();
            header('Location: admin.php?tab=products&msg=updated');
            exit;
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        } catch (mysqli_sql_exception $e) {
            error_log($e->getMessage());
            $error = ($e->getCode() === 1062) ? 'Mã SKU đã tồn tại.' : 'Không thể cập nhật sản phẩm. Kiểm tra danh mục và dữ liệu.';
        }
        if ($error !== '' && $newImage !== null) {
            @unlink(__DIR__ . '/../image/uploads/' . $newImage);
        }
    }
}
$categories = $conn->query('SELECT id, name FROM categories ORDER BY name ASC');
?>
<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sửa thông tin sản phẩm</title><link rel="stylesheet" href="../css/style.css"><link rel="icon" href="../image/kaidoLogomini.png">
<style>
.form-box{max-width:750px;margin:25px auto;padding:28px;background:#252525;border-radius:12px;color:#eee}
.form-box label{display:block;margin-top:15px;margin-bottom:5px}
.form-box input,.form-box select,.form-box textarea{width:100%;box-sizing:border-box;padding:10px;background:#171717;color:white;border:1px solid #666;border-radius:6px}
.form-box button{padding:12px 20px;margin-top:18px;border:0;background:#21895b;color:white;cursor:pointer;border-radius:6px}
.form-box .error{color:#ffb0b0}.form-box a{color:#a4ccff}.form-box img{max-width:200px;max-height:140px;object-fit:contain}

/* Hỗ trợ Light Mode cho các trang quản trị */
body.light-mode { background-color: #f1f5f9 !important; color: #0f172a !important; }
body.light-mode .form-box { background: #ffffff !important; border: 1px solid #cbd5e1 !important; color: #0f172a !important; box-shadow: 0 4px 15px rgba(0,0,0,0.05) !important; }
body.light-mode .form-box input, body.light-mode .form-box select, body.light-mode .form-box textarea { background: #f8fafc !important; color: #0f172a !important; border: 1px solid #cbd5e1 !important; }
body.light-mode h1, body.light-mode h2, body.light-mode h3, body.light-mode label { color: #0f172a !important; }
body.light-mode .theme-toggle-btn { background: rgba(0,0,0,0.08) !important; border: 1px solid rgba(0,0,0,0.2) !important; color: #0f172a !important; }
</style></head><body>

<div style="text-align: right; padding: 15px 30px;">
    <button id="themeToggle" class="theme-toggle-btn" onclick="toggleTheme()" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 6px 12px; border-radius: 6px; cursor: pointer;">
        🌙 Dark
    </button>
</div>
<main class="form-box">
<h1>SỬA SẢN PHẨM #<?= (int)$product['id'] ?></h1>
<p><a href="admin.php?tab=products">← Danh sách sản phẩm</a> | <a href="thongso.php?product_id=<?= (int)$id ?>">Thông số kỹ thuật</a></p>
<?php if ($error !== ''): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<?= admin_csrf_field() ?>
<label>Tên sản phẩm</label><input name="product_name" required maxlength="255" value="<?= h($data['product_name']) ?>">
<label>Mã SKU</label><input name="sku" required maxlength="50" value="<?= h($data['sku']) ?>">
<label>Danh mục</label><select name="category_id" required><option value="">-- Chọn danh mục --</option>
<?php while ($category = $categories->fetch_assoc()): ?>
<option value="<?= (int)$category['id'] ?>" <?= (int)$category['id'] === (int)$data['category_id'] ? 'selected' : '' ?>><?= h($category['name']) ?></option>
<?php endwhile; ?></select>
<label>Giá bán (VNĐ)</label><input type="number" name="price" min="0" step="1" required value="<?= h($data['price']) ?>">
<label>Tổng số lượng tồn kho</label><input type="number" name="stock_qty" min="0" step="1" required value="<?= h($data['stock_qty']) ?>">
<label>Trạng thái</label><select name="status">
<?php foreach (['active' => 'Đang bán', 'inactive' => 'Ngừng bán (ẩn khỏi cửa hàng)'] as $value => $label): ?>
<option value="<?= h($value) ?>" <?= $data['status'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
<?php endforeach; ?></select>
<label>Mô tả</label><textarea name="description" rows="5"><?= h($data['description']) ?></textarea>
<label>Hình ảnh hiện tại</label>
<?php if (!empty($product['image_path']) && is_file(__DIR__ . '/../image/uploads/' . basename($product['image_path']))): ?>
<img src="../image/uploads/<?= h(basename($product['image_path'])) ?>" alt="Hình ảnh sản phẩm">
<?php else: ?><p>Chưa có ảnh hoặc ảnh cũ không nằm trong thư mục image/uploads.</p><?php endif; ?>
<label>Đổi hình ảnh (để trống nếu giữ ảnh cũ)</label><input type="file" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp">
<button type="submit">Lưu thay đổi</button>
</form>
</main></body><script src="../js/slide.js"></script></html>
