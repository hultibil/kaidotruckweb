<?php
require_once __DIR__ . '/baove.php';

$productId = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
if (!$productId || $productId < 1) {
    http_response_code(400);
    exit('Mã sản phẩm không hợp lệ.');
}
$stmt = $conn->prepare('SELECT id, product_name FROM products WHERE id = ?');
$stmt->bind_param('i', $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$product) {
    http_response_code(404);
    exit('Không tìm thấy sản phẩm.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $names = $_POST['spec_name'] ?? [];
    $values = $_POST['spec_value'] ?? [];
    if (!is_array($names) || !is_array($values) || count($names) !== count($values) || count($names) > 100) {
        $error = 'Danh sách thông số không hợp lệ.';
    } else {
        $specs = [];
        foreach ($names as $i => $name) {
            if (!is_string($name) || !is_string($values[$i] ?? null)) {
                $error = 'Dữ liệu thông số không hợp lệ.';
                break;
            }
            $name = trim($name);
            $value = trim($values[$i]);
            if ($name === '' && $value === '') {
                continue;
            }
            if ($name === '' || $value === '' || (function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name)) > 100 || (function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value)) > 255) {
                $error = 'Mỗi thông số phải có tên (tối đa 100 ký tự) và giá trị (tối đa 255 ký tự).';
                break;
            }
            $specs[] = [$name, $value];
        }
    }
    if ($error === '') {
        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare('DELETE FROM product_specs WHERE product_id = ?');
            $stmt->bind_param('i', $productId);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare('INSERT INTO product_specs (product_id, spec_name, spec_value) VALUES (?, ?, ?)');
            foreach ($specs as [$name, $value]) {
                $stmt->bind_param('iss', $productId, $name, $value);
                $stmt->execute();
            }
            $stmt->close();
            $conn->commit();
            header('Location: thongso.php?product_id=' . $productId . '&msg=saved');
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
            $error = 'Không thể lưu thông số. Kiểm tra bảng product_specs trong MySQL.';
        }
    }
}

$specs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spec_name'], $_POST['spec_value']) && is_array($_POST['spec_name']) && is_array($_POST['spec_value'])) {
    foreach ($_POST['spec_name'] as $i => $name) {
        if (is_string($name) && is_string($_POST['spec_value'][$i] ?? null)) {
            $specs[] = ['spec_name' => $name, 'spec_value' => $_POST['spec_value'][$i]];
        }
    }
} else {
    $stmt = $conn->prepare('SELECT spec_name, spec_value FROM product_specs WHERE product_id = ?');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $specs[] = $row;
    }
    $stmt->close();
}
$specs[] = ['spec_name' => '', 'spec_value' => ''];
?>
<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Thông số kỹ thuật sản phẩm</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/darklight.css">
<link rel="icon" href="../image/kaidoLogomini.png">
<style>
.spec-box{max-width:950px;margin:30px auto;padding:28px;border-radius:12px;background:#242424;color:#eee}
.spec-box a{color:#9dc6ff}
.spec-box input{padding:10px;width:100%;box-sizing:border-box;border:1px solid #666;background:#101010;color:#fff}
.spec-box table{width:100%;border-collapse:collapse}
.spec-box td,.spec-box th{padding:10px;border-bottom:1px solid #555;text-align:left}
.spec-box button{padding:10px 15px;background:#27834e;color:white;border:0;cursor:pointer;border-radius:5px}
.spec-box .remove{background:#b53645}
.spec-box .error{color:#ffb3b3}.spec-box .ok{color:#adeabc}

/* Hỗ trợ Light Mode */
body.light-mode { background-color: #f1f5f9 !important; color: #0f172a !important; }
body.light-mode .spec-box { background: #ffffff !important; border: 1px solid #cbd5e1 !important; color: #0f172a !important; box-shadow: 0 4px 15px rgba(0,0,0,0.05) !important; }
body.light-mode .spec-box input { background: #f8fafc !important; color: #0f172a !important; border: 1px solid #cbd5e1 !important; }
body.light-mode .spec-box th { background: #f8fafc !important; color: #475569 !important; }
body.light-mode .spec-box td { border-bottom: 1px solid #e2e8f0 !important; color: #1e293b !important; }
body.light-mode h1, body.light-mode h2 { color: #0f172a !important; }
body.light-mode .theme-toggle-btn { background: rgba(0,0,0,0.08) !important; border: 1px solid rgba(0,0,0,0.2) !important; color: #0f172a !important; }
</style></head><body>

<div style="text-align: right; padding: 15px 30px;">
    <button id="themeToggle" class="theme-toggle-btn" onclick="toggleTheme()" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 6px 12px; border-radius: 6px; cursor: pointer;">
        🌙 Dark
    </button>
</div>
<main class="spec-box">
<h1>THÔNG SỐ KỸ THUẬT</h1>
<h2><?= h($product['product_name']) ?></h2>
<p><a href="suasanpham.php?id=<?= (int)$productId ?>">← Sửa sản phẩm</a> | <a href="admin.php">Quản trị</a> | <a href="../sanpham/chitietsanpham.php?id=<?= (int)$productId ?>">Xem trang khách</a></p>
<?php if (($_GET['msg'] ?? '') === 'saved'): ?><p class="ok">Đã lưu thông số kỹ thuật.</p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
<p>Nhập tên thông số và giá trị. Ví dụ: Điện áp hoạt động — 5V. Xóa một dòng rồi nhấn Lưu để bỏ thông số đó.</p>
<form method="post"><?= admin_csrf_field() ?>
<table><thead><tr><th>Tên thông số</th><th>Giá trị</th><th>Thao tác</th></tr></thead><tbody id="spec-rows">
<?php foreach ($specs as $spec): ?>
<tr><td><input name="spec_name[]" maxlength="100" value="<?= h($spec['spec_name']) ?>" placeholder="Ví dụ: Điện áp"></td>
<td><input name="spec_value[]" maxlength="255" value="<?= h($spec['spec_value']) ?>" placeholder="Ví dụ: 5V"></td>
<td><button type="button" class="remove" onclick="this.closest('tr').remove()">Xóa dòng</button></td></tr>
<?php endforeach; ?></tbody></table>
<p><button type="button" onclick="addRow()">+ Thêm thông số</button> <button type="submit">Lưu tất cả thông số</button></p>
</form>
<script>
function addRow() {
    const tr = document.createElement('tr');
    tr.innerHTML = '<td><input name="spec_name[]" maxlength="100" placeholder="Tên thông số"></td><td><input name="spec_value[]" maxlength="255" placeholder="Giá trị"></td><td><button type="button" class="remove" onclick="this.closest(\'tr\').remove()">Xóa dòng</button></td>';
    document.getElementById('spec-rows').appendChild(tr);
}
</script>
</main></body><script src="../js/slide.js"></script></html>
