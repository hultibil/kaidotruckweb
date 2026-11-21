<?php
session_start();
require_once __DIR__ . '/../db.php';

function tv_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
if (empty($_SESSION['consultation_csrf'])) $_SESSION['consultation_csrf'] = bin2hex(random_bytes(32));
$errors = []; $values = ['full_name'=>'','email'=>'','phone'=>'','subject'=>'','message'=>''];
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
if ($userId) {
    $st = $conn->prepare('SELECT full_name, email, phone FROM users WHERE id = ?'); $st->bind_param('i', $userId); $st->execute();
    if ($profile = $st->get_result()->fetch_assoc()) $values = array_merge($values, array_intersect_key($profile, $values));
    $st->close();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $unused) $values[$key] = trim((string)($_POST[$key] ?? ''));
    if (!hash_equals($_SESSION['consultation_csrf'], (string)($_POST['csrf_token'] ?? ''))) $errors['form'] = 'Phiên gửi đã hết hạn. Hãy tải lại trang và thử lại.';
    if (mb_strlen($values['full_name']) < 2 || mb_strlen($values['full_name']) > 150) $errors['full_name'] = 'Nhập họ tên từ 2 đến 150 ký tự.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 150) $errors['email'] = 'Nhập địa chỉ email hợp lệ.';
    if (!preg_match('/^[0-9+() .-]{8,20}$/', $values['phone'])) $errors['phone'] = 'Nhập số điện thoại hợp lệ (8–20 ký tự).';
    if (mb_strlen($values['subject']) < 2 || mb_strlen($values['subject']) > 150) $errors['subject'] = 'Nhập chủ đề từ 2 đến 150 ký tự.';
    if (mb_strlen($values['message']) < 10 || mb_strlen($values['message']) > 3000) $errors['message'] = 'Mô tả nhu cầu từ 10 đến 3.000 ký tự.';
    if (!$errors) {
        $st = $conn->prepare('INSERT INTO consultation_requests (user_id, full_name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?, ?)');
        $st->bind_param('isssss', $userId, $values['full_name'], $values['email'], $values['phone'], $values['subject'], $values['message']); $st->execute(); $st->close();
        $_SESSION['consultation_sent'] = true; header('Location: tuvan.php?sent=1'); exit;
    }
}
$sent = isset($_GET['sent']) && !empty($_SESSION['consultation_sent']); unset($_SESSION['consultation_sent']);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Gửi yêu cầu tư vấn về linh kiện với shop| Kaido</title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="styletuvan.css">
    <script>
        // Áp dụng theme ngay trước khi render để tránh chớp màn hình
        (function() {
            const saved = localStorage.getItem('glassy-theme') || localStorage.getItem('kaido_theme') || 'light';
            document.documentElement.setAttribute('data-glassy-theme', saved);
        })();
    </script>
</head>
<body>
    <main class="wrap" id="main-content">
        <div class="topbar">
            <a class="brand" href="../index.php">// FORM ĐIỀN TƯ VẤN</a>
            <div class="topbar-right">
                <button type="button" id="themeToggle" class="theme-btn" onclick="toggleTvTheme()">☀️ Light</button>
                <a class="back" href="../index.php">← Quay lại tổng quan</a>
            </div>
        </div>

        <section class="hero">
            <h1>Gửi yêu cầu tư vấn</h1>
            <p>Để lại nhu cầu của bạn. Đội ngũ Kaido sẽ tiếp nhận và liên hệ lại qua số điện thoại hoặc email.</p>
        </section>

        <section class="card" aria-labelledby="form-title">
            <h2 id="form-title">Thông tin cần tư vấn</h2>
            <?php if ($sent): ?>
                <div class="notice ok" role="status">Yêu cầu của bạn đã được gửi. Cảm ơn bạn đã liên hệ Kaido.</div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="summary" role="alert" tabindex="-1">
                    <h2>Cần kiểm tra lại thông tin</h2>
                    <ul>
                        <?php foreach ($errors as $field => $message): ?>
                            <li><a href="#<?= tv_h($field) ?>"><?= tv_h($message) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= tv_h($_SESSION['consultation_csrf']) ?>">
                <div class="grid">
                    <div class="field">
                        <label for="full_name">Họ và tên <span class="required">*</span></label>
                        <input id="full_name" name="full_name" autocomplete="name" value="<?= tv_h($values['full_name']) ?>" aria-describedby="full_name-help <?= isset($errors['full_name'])?'full_name-error':'' ?>" class="<?= isset($errors['full_name'])?'invalid':'' ?>">
                        <small class="hint" id="full_name-help">Dùng để xưng hô khi liên hệ.</small>
                        <?php if(isset($errors['full_name'])):?><small class="field-error" id="full_name-error"><?= tv_h($errors['full_name']) ?></small><?php endif;?>
                    </div>
                    <div class="field">
                        <label for="phone">Số điện thoại <span class="required">*</span></label>
                        <input id="phone" name="phone" type="tel" autocomplete="tel" value="<?= tv_h($values['phone']) ?>" class="<?= isset($errors['phone'])?'invalid':'' ?>" aria-describedby="phone-help <?= isset($errors['phone'])?'phone-error':'' ?>">
                        <small class="hint" id="phone-help">Số để nhân viên gọi lại.</small>
                        <?php if(isset($errors['phone'])):?><small class="field-error" id="phone-error"><?= tv_h($errors['phone']) ?></small><?php endif;?>
                    </div>
                </div>

                <div class="field">
                    <label for="email">Email <span class="required">*</span></label>
                    <input id="email" name="email" type="email" autocomplete="email" value="<?= tv_h($values['email']) ?>" class="<?= isset($errors['email'])?'invalid':'' ?>" aria-describedby="email-help <?= isset($errors['email'])?'email-error':'' ?>">
                    <small class="hint" id="email-help">Chúng tôi có thể gửi thông tin chi tiết qua email.</small>
                    <?php if(isset($errors['email'])):?><small class="field-error" id="email-error"><?= tv_h($errors['email']) ?></small><?php endif;?>
                </div>

                <div class="field">
                    <label for="subject">Bạn cần tư vấn về <span class="required">*</span></label>
                    <input id="subject" name="subject" maxlength="150" placeholder="Ví dụ: Giá sản phẩm, thắc mắc về linh kiện, bảo hành,.." value="<?= tv_h($values['subject']) ?>" class="<?= isset($errors['subject'])?'invalid':'' ?>" aria-describedby="subject-error">
                    <?php if(isset($errors['subject'])):?><small class="field-error" id="subject-error"><?= tv_h($errors['subject']) ?></small><?php endif;?>
                </div>

                <div class="field">
                    <label for="message">Nội dung cần tư vấn <span class="required">*</span></label>
                    <textarea id="message" name="message" maxlength="3000" placeholder="Hãy mô tả sản phẩm hoặc vấn đề bạn đang quan tâm..." class="<?= isset($errors['message'])?'invalid':'' ?>" aria-describedby="message-help <?= isset($errors['message'])?'message-error':'' ?>"><?= tv_h($values['message']) ?></textarea>
                    <small class="hint" id="message-help">Nêu càng rõ, chúng tôi hỗ trợ càng nhanh.</small>
                    <?php if(isset($errors['message'])):?><small class="field-error" id="message-error"><?= tv_h($errors['message']) ?></small><?php endif;?>
                </div>

                <div class="actions">
                    <button type="submit">Gửi yêu cầu tư vấn</button>
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

        function toggleTvTheme() {
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