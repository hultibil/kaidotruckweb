<?php
require_once __DIR__ . '/baove.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $name = trim((string)($_POST['name'] ?? ''));

    if (in_array($action, ['add', 'edit'], true) && ($name === '' || (function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name)) > 100)) {
        $error = 'Tên danh mục phải có từ 1 đến 100 ký tự.';
    } elseif (in_array($action, ['edit', 'delete'], true) && (!$id || $id < 1)) {
        $error = 'Mã danh mục không hợp lệ.';
    } else {
        try {
            if ($action === 'add') {
                $stmt = $conn->prepare('INSERT INTO categories (name) VALUES (?)');
                $stmt->bind_param('s', $name);
                $stmt->execute();
                $stmt->close();
                header('Location: danhmuc.php?msg=added');
                exit;
            }
            if ($action === 'edit') {
                $stmt = $conn->prepare('UPDATE categories SET name = ? WHERE id = ?');
                $stmt->bind_param('si', $name, $id);
                $stmt->execute();
                $stmt->close();
                header('Location: danhmuc.php?msg=updated');
                exit;
            }
            if ($action === 'delete') {
                $stmt = $conn->prepare('SELECT COUNT(*) AS n FROM products WHERE category_id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $n = (int)$stmt->get_result()->fetch_assoc()['n'];
                $stmt->close();
                if ($n > 0) {
                    $error = "Danh mục đang có $n sản phẩm. Hãy chuyển sản phẩm sang danh mục khác trước khi xóa.";
                } else {
                    $stmt = $conn->prepare('DELETE FROM categories WHERE id = ?');
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->close();
                    header('Location: danhmuc.php?msg=deleted');
                    exit;
                }
            }
            if (!in_array($action, ['add', 'edit', 'delete'], true)) {
                $error = 'Thao tác không hợp lệ.';
            }
        } catch (mysqli_sql_exception $e) {
            error_log($e->getMessage());
            $error = 'Không thể lưu danh mục. Tên có thể bị trùng hoặc dữ liệu đang được sử dụng.';
        }
    }
}

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$editName = '';
if ($editId && $editId > 0) {
    $stmt = $conn->prepare('SELECT name FROM categories WHERE id = ?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $editName = $row['name'];
    } else {
        $editId = null;
    }
}
$categories = $conn->query('SELECT c.id, c.name, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id, c.name ORDER BY c.id ASC');
$messages = ['added' => 'Đã thêm danh mục thành công.', 'updated' => 'Đã cập nhật danh mục thành công.', 'deleted' => 'Đã xóa danh mục thành công.'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Danh Mục - Kaido Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../image/kaidoLogomini.png">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0;
            background: #0f172a; color: #f1f5f9;
            font-family: 'HarmonyOS Sans', system-ui, sans-serif;
            display: flex; min-height: 100vh;
        }

        /* 1. SIDEBAR TRÁI */
        .admin-sidebar {
            width: 260px; background: #1e293b;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            display: flex; flex-direction: column; flex-shrink: 0; z-index: 10;
        }
        .brand-logo-container {
            padding: 20px 15px; text-align: center;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .brand-logo-container img { max-width: 140px; height: auto; border-radius: 8px; }
        .brand-name { font-size: 14px; font-weight: bold; color: #94a3b8; margin-top: 8px; letter-spacing: 1px; }

        .admin-profile {
            padding: 15px 20px; display: flex; align-items: center; gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .avatar-circle {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, #ff416c, #ff4b2b);
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; font-size: 18px; color: white;
            box-shadow: 0 2px 8px rgba(255, 65, 108, 0.4);
        }

        .sidebar-menu { padding: 15px 10px; flex: 1; }
        .menu-heading { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: bold; padding: 10px 15px 5px; letter-spacing: 0.5px; }
        .sidebar-menu a {
            display: flex; align-items: center; padding: 12px 16px; color: #cbd5e1;
            text-decoration: none; border-radius: 8px; margin-bottom: 6px; font-weight: 500; font-size: 14px; transition: all 0.25s ease;
        }
        .sidebar-menu a:hover { background: rgba(255, 255, 255, 0.06); color: #fff; transform: translateX(4px); }
        .sidebar-menu a.active { background: linear-gradient(90deg, #ff416c, #ff4b2b); color: white; box-shadow: 0 4px 12px rgba(255, 65, 108, 0.3); }

        /* 2. KHU VỰC HIỂN THỊ NỘI DUNG */
        .admin-main { flex: 1; display: flex; flex-direction: column; overflow-x: hidden; background: #0f172a; }
        .main-header {
            height: 65px; background: #1e293b; border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex; align-items: center; justify-content: space-between; padding: 0 30px;
        }
        .header-title { font-size: 18px; font-weight: bold; color: #f8fafc; }
        .topbar-actions a { color: #94a3b8; text-decoration: none; margin-left: 18px; font-size: 14px; }
        .topbar-actions a:hover { color: #ff416c; }

        .content-body { padding: 30px; flex: 1; }
        .card-box {
            background: #1e293b; border-radius: 12px; padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.05); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }

        /* Forms & Inputs */
        .category-form { display: flex; gap: 12px; align-items: center; margin-bottom: 25px; flex-wrap: wrap; }
        .category-form input[type="text"] {
            flex: 1; min-width: 260px; padding: 10px 14px; border-radius: 8px;
            border: 1px solid #334155; background: #0f172a; color: white; font-size: 14px;
        }
        .btn-action {
            padding: 9px 18px; border-radius: 8px; text-decoration: none; color: white;
            font-size: 14px; font-weight: 500; border: none; cursor: pointer; display: inline-flex; align-items: center; transition: opacity 0.2s;
        }
        .btn-save { background: #10b981; }
        .btn-delete { background: #ef4444; }
        .btn-cancel { background: #64748b; }
        .btn-action:hover { opacity: 0.85; }

        /* Tables */
        table.admin-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 15px; }
        table.admin-table th, table.admin-table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.06); font-size: 14px; }
        table.admin-table th { background: rgba(15, 23, 42, 0.6); color: #94a3b8; font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
        table.admin-table tr:hover { background: rgba(255, 255, 255, 0.02); }

        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; color: #34d399; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; color: #fca5a5; }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .admin-sidebar { width: 100%; }
            .content-body { padding: 15px; }
        }
    </style>
</head>
<body>

    <!-- 1. SIDEBAR TRÁI (ADMINLTE LAYOUT) -->
    <aside class="admin-sidebar">
        <div class="brand-logo-container">
            <a href="../index.php">
                <img src="../image/logo.png" alt="Logo Kaido Shop" onerror="this.onerror=null; this.src='../image/kaidologo.jpg';">
            </a>
            <div class="brand-name">KAIDO ADMIN HUB</div>
        </div>

        <div class="admin-profile">
            <div class="avatar-circle">
                <?= strtoupper(substr(h($admin_username), 0, 1)) ?>
            </div>
            <div class="admin-info-text">
                <strong><?= h($admin_username) ?></strong>
                <span style="color:#64748b; font-size:12px; display:block;">Quản trị viên</span>
            </div>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-heading">Bảng Điều Khiển</div>
            <a href="admin.php?tab=products">Quản lý Sản phẩm</a>
            <a href="admin.php?tab=orders">Quản lý Đơn hàng</a>
            <a href="admin.php?tab=users">Quản lý Người dùng</a>

            <div class="menu-heading" style="margin-top: 15px;">Kho & Danh Mục</div>
            <a href="danhmuc.php" class="active">Quản lý Danh mục</a>
            <a href="khohang.php">Tồn kho & Cảnh báo</a>
        </nav>
    </aside>

    <!-- 2. MAIN CONTENT BÊN PHẢI -->
    <main class="admin-main">
        <header class="main-header">
            <div class="header-title">QUẢN LÝ DANH MỤC SẢN PHẨM</div>

            <div class="topbar-actions" style="display: flex; align-items: center; gap: 10px;">
                <!-- NÚT BẤM CHUYỂN ĐỔI DARK / LIGHT MODE -->
                <button id="themeToggle" class="theme-toggle-btn" onclick="toggleTheme()" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 6px 12px; border-radius: 6px; cursor: pointer;">
                    🌙 Dark
                </button>

            <div class="topbar-actions">
                <a href="../index.php" target="_blank">🌐 Trang chủ Cửa hàng</a>
                <a href="../Taikhoan/dangxuat.php" style="color:#ef4444;">🚪 Đăng xuất</a>
            </div>
        </header>

        <div class="content-body">
            <div class="card-box">
                
                <?php if (isset($messages[$_GET['msg'] ?? ''])): ?>
                    <div class="alert-success"><?= h($messages[$_GET['msg']]) ?></div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="alert-error"><?= h($error) ?></div>
                <?php endif; ?>

                <h3 style="margin-top:0; color:#f8fafc; font-size:18px;">
                    <?= $editId ? '✏️ Sửa danh mục #' . (int)$editId : '➕ Thêm danh mục mới' ?>
                </h3>

                <form method="post" class="category-form">
                    <?= admin_csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editId ? 'edit' : 'add' ?>">
                    <?php if ($editId): ?>
                        <input type="hidden" name="id" value="<?= (int)$editId ?>">
                    <?php endif; ?>
                    
                    <input type="text" name="name" required maxlength="100" placeholder="Nhập tên danh mục..." value="<?= h($editName) ?>">
                    
                    <button type="submit" class="btn-action btn-save">
                        <?= $editId ? 'Lưu thay đổi' : 'Thêm danh mục' ?>
                    </button>
                    <?php if ($editId): ?>
                        <a href="danhmuc.php" class="btn-action btn-cancel">Hủy bỏ</a>
                    <?php endif; ?>
                </form>

                <hr style="border: 0; border-top: 1px solid rgba(255,255,255,0.08); margin: 25px 0;">

                <h3 style="color:#f8fafc; font-size:18px; margin-bottom:15px;">Danh sách danh mục hiện có</h3>
                <div style="overflow-x:auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên danh mục</th>
                                <th>Số sản phẩm liên kết</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($category = $categories->fetch_assoc()): ?>
                            <tr>
                                <td><?= (int)$category['id'] ?></td>
                                <td><strong style="color:#38bdf8;"><?= h($category['name']) ?></strong></td>
                                <td>
                                    <span style="padding: 3px 10px; border-radius: 12px; background: rgba(59, 130, 246, 0.2); color: #60a5fa; font-weight: bold; font-size: 12px;">
                                        <?= (int)$category['product_count'] ?> sản phẩm
                                    </span>
                                </td>
                                <td>
                                    <a href="danhmuc.php?edit=<?= (int)$category['id'] ?>" class="btn-action" style="background:#3b82f6; padding: 5px 12px; font-size: 13px;">Sửa</a>
                                    
                                    <form method="post" style="display:inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa danh mục này không?');">
                                        <?= admin_csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                        <button type="submit" class="btn-action btn-delete" style="padding: 5px 12px; font-size: 13px;">Xóa</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <p style="opacity: 0.6; font-size: 13px; margin-top: 20px; color: #94a3b8;">
                    💡 <em>Lưu ý: Không thể xóa danh mục đang chứa sản phẩm. Hãy chuyển các sản phẩm sang danh mục khác trước khi thực hiện xóa.</em>
                </p>

            </div>
        </div>
    </main>
</body>
<script src="../js/slide.js"></script>
</html>