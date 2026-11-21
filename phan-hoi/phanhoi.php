<?php
session_start();
require_once __DIR__ . '/../db.php';

function ph_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['feedback_csrf'])) $_SESSION['feedback_csrf'] = bin2hex(random_bytes(32));
$categories = [
    'website_error'   => 'Báo lỗi website',
    'product_service' => 'Sản phẩm hoặc dịch vụ',
    'feature_idea'    => 'Đề xuất tính năng',
    'other'           => 'Nội dung khác'
];
$errors = [];
$values = ['full_name' => '', 'email' => '', 'category' => 'website_error', 'message' => ''];
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if ($userId) {
    $st = $conn->prepare('SELECT full_name, email FROM users WHERE id = ?');
    $st->bind_param('i', $userId);
    $st->execute();
    if ($profile = $st->get_result()->fetch_assoc()) $values = array_merge($values, $profile);
    $st->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $unused) $values[$key] = trim((string)($_POST[$key] ?? ''));
    if (!hash_equals($_SESSION['feedback_csrf'], (string)($_POST['csrf_token'] ?? ''))) $errors['form'] = 'Phiên gửi đã hết hạn. Hãy tải lại trang và thử lại.';
    if (mb_strlen($values['full_name']) < 2 || mb_strlen($values['full_name']) > 150) $errors['full_name'] = 'Nhập họ tên từ 2 đến 150 ký tự.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 150) $errors['email'] = 'Nhập địa chỉ email hợp lệ.';
    if (!isset($categories[$values['category']])) $errors['category'] = 'Hãy chọn loại phản hồi.';
    if (mb_strlen($values['message']) < 10 || mb_strlen($values['message']) > 3000) $errors['message'] = 'Nhập phản hồi từ 10 đến 3.000 ký tự.';
    if (!$errors) {
        $st = $conn->prepare('INSERT INTO system_feedback (user_id, full_name, email, category, message) VALUES (?, ?, ?, ?, ?)');
        $st->bind_param('issss', $userId, $values['full_name'], $values['email'], $values['category'], $values['message']);
        $st->execute();
        $st->close();
        $_SESSION['feedback_sent'] = true;
        header('Location: phanhoi.php?sent=1');
        exit;
    }
}
$sent = isset($_GET['sent']) && !empty($_SESSION['feedback_sent']);
unset($_SESSION['feedback_sent']);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Gửi phản hồi | Kaido</title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="phanhoicss.css">
    <script>
        (function() {
            const saved = localStorage.getItem('glassy-theme') || localStorage.getItem('kaido_theme') || 'light';
            document.documentElement.setAttribute('data-glassy-theme', saved);
        })();
    </script>
</head>
<body>
    <main class="wrap" id="main-content">
        <div class="topbar">
            <a class="brand" href="../index.php">// FORM ĐIỀN PHẢN HỒI</a>
            <div class="topbar-right">
                <button type="button" id="themeToggle" class="theme-btn" onclick="togglePhTheme()">☀️ Light</button>
                <a class="back" href="../index.php">← Quay lại trang tổng quan</a>
            </div>
        </div>

        <section class="hero">
            <h1>Gửi phản hồi đến hệ thống</h1>
            <p>Hãy cho Kaido biết lỗi bạn gặp phải hoặc ý tưởng giúp website tốt hơn.</p>
        </section>

        <section class="card" aria-labelledby="form-title">
            <h2 id="form-title">Nội dung phản hồi</h2>
            <?php if ($sent): ?>
                <div class="notice ok" role="status">Phản hồi của bạn đã được ghi nhận. Cảm ơn bạn đã giúp Kaido cải thiện.</div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="summary" role="alert" tabindex="-1">
                    <h2>Cần kiểm tra lại thông tin</h2>
                    <ul>
                        <?php foreach ($errors as $field => $message): ?>
                            <li><a href="#<?= ph_h($field) ?>"><?= ph_h($message) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= ph_h($_SESSION['feedback_csrf']) ?>">
                <div class="grid">
                    <div class="field">
                        <label for="full_name">Họ và tên <span class="required">*</span></label>
                        <input id="full_name" name="full_name" autocomplete="name" value="<?= ph_h($values['full_name']) ?>" class="<?= isset($errors['full_name']) ? 'invalid' : '' ?>" aria-describedby="<?= isset($errors['full_name']) ? 'full_name-error' : '' ?>">
                        <?php if (isset($errors['full_name'])): ?><small class="field-error" id="full_name-error"><?= ph_h($errors['full_name']) ?></small><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="email">Email <span class="required">*</span></label>
                        <input id="email" name="email" type="email" autocomplete="email" value="<?= ph_h($values['email']) ?>" class="<?= isset($errors['email']) ? 'invalid' : '' ?>" aria-describedby="<?= isset($errors['email']) ? 'email-error' : '' ?>">
                        <?php if (isset($errors['email'])): ?><small class="field-error" id="email-error"><?= ph_h($errors['email']) ?></small><?php endif; ?>
                    </div>
                </div>

                <div class="field">
                    <label for="category">Loại phản hồi <span class="required">*</span></label>
                    <select id="category" name="category" class="<?= isset($errors['category']) ? 'invalid' : '' ?>" aria-describedby="category-help <?= isset($errors['category']) ? 'category-error' : '' ?>">
                        <?php foreach ($categories as $key => $label): ?>
                            <option value="<?= ph_h($key) ?>" <?= $values['category'] === $key ? 'selected' : '' ?>><?= ph_h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="hint" id="category-help">Chọn loại phù hợp để phản hồi được chuyển đúng nơi.</small>
                    <?php if (isset($errors['category'])): ?><small class="field-error" id="category-error"><?= ph_h($errors['category']) ?></small><?php endif; ?>
                </div>

                <div class="field">
                    <label for="message">Nội dung phản hồi <span class="required">*</span></label>
                    <textarea id="message" name="message" maxlength="3000" placeholder="Mô tả lỗi, bước bạn đã thực hiện hoặc ý tưởng muốn đề xuất..." class="<?= isset($errors['message']) ? 'invalid' : '' ?>" aria-describedby="message-help <?= isset($errors['message']) ? 'message-error' : '' ?>"><?= ph_h($values['message']) ?></textarea>
                    <small class="hint" id="message-help">Nếu báo lỗi, hãy nêu trang đang dùng và điều xảy ra.</small>
                    <?php if (isset($errors['message'])): ?><small class="field-error" id="message-error"><?= ph_h($errors['message']) ?></small><?php endif; ?>
                </div>

                <div class="actions">
                    <button type="submit">Gửi phản hồi</button>
                    <span class="hint">Các mục có dấu * là bắt buộc.</span>
                </div>
            </form>
        </section>
    </main>

    <script>
        function syncThemeBtn(theme) {
            const btn = document.getElementById('themeToggle');
            if (!btn) return;
            btn.textContent = theme === 'dark' ? '🌙 Dark' : '☀️ Light';
        }

        function togglePhTheme() {
            const current = document.documentElement.getAttribute('data-glassy-theme') === 'dark' ? 'dark' : 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-glassy-theme', next);
            localStorage.setItem('glassy-theme', next);
            localStorage.setItem('kaido_theme', next);
            syncThemeBtn(next);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const current = document.documentElement.getAttribute('data-glassy-theme') || 'light';
            syncThemeBtn(current);
        });
    </script>
</body>
</html>