<?php
session_start();
require_once __DIR__ . '/../db.php';

function lsg_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Chuyển hướng nếu chưa đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: dangnhap.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Nhãn trạng thái & phân loại đồng bộ với trang Admin
$tvStates = [
    'new'       => ['Mới nhận', 'status-new'],
    'contacted' => ['Đã liên hệ', 'status-info'],
    'closed'    => ['Đã đóng', 'status-ok'],
];

$phStates = [
    'new'       => ['Mới nhận', 'status-new'],
    'reviewing' => ['Đang xem xét', 'status-warn'],
    'resolved'  => ['Đã xử lý', 'status-ok'],
];

$phCategories = [
    'website_error'   => 'Báo lỗi website',
    'product_service' => 'Sản phẩm / Dịch vụ',
    'feature_idea'    => 'Đề xuất tính năng',
    'other'           => 'Nội dung khác',
];

$dsTuVan = [];
$dsPhanHoi = [];
$dbError = '';

try {
    // Lấy lịch sử yêu cầu tư vấn của user
    $st1 = $conn->prepare('SELECT id, full_name, phone, email, subject, message, status, created_at FROM consultation_requests WHERE user_id = ? ORDER BY created_at DESC');
    $st1->bind_param('i', $userId);
    $st1->execute();
    $dsTuVan = $st1->get_result()->fetch_all(MYSQLI_ASSOC);
    $st1->close();

    // Lấy lịch sử phản hồi hệ thống của user
    $st2 = $conn->prepare('SELECT id, full_name, email, category, message, status, created_at FROM system_feedback WHERE user_id = ? ORDER BY created_at DESC');
    $st2->bind_param('i', $userId);
    $st2->execute();
    $dsPhanHoi = $st2->get_result()->fetch_all(MYSQLI_ASSOC);
    $st2->close();
} catch (Throwable $e) {
    $dbError = 'Không thể tải dữ liệu lịch sử lúc này. Vui lòng kiểm tra lại cơ sở dữ liệu.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử tư vấn &amp; phản hồi | Kaido</title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/glassy.css">
    <script src="../js/glassy-theme.js"></script>
    <style>
        .lsg-wrap {
            max-width: 1100px;
            margin: 0 auto;
        }
        .lsg-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 24px;
        }
        .lsg-header h1 {
            margin: 0 0 6px;
            font-size: clamp(24px, 4vw, 34px);
            letter-spacing: -0.03em;
        }
        .lsg-header p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }
        .lsg-quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .lsg-quick-links a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            border-radius: 99px;
            background: var(--primary-gradient);
            color: #fff !important;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 6px 16px rgba(8, 97, 195, 0.25);
            transition: transform 0.16s ease;
        }
        .lsg-quick-links a.secondary {
            background: var(--paper);
            color: var(--ink) !important;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }
        .lsg-quick-links a:hover {
            transform: translateY(-2px);
        }

        /* Tabs chuyển đổi giữa Tư vấn và Phản hồi */
        .lsg-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .lsg-tab-btn {
            padding: 10px 18px;
            border-radius: 99px;
            border: 1px solid var(--border);
            background: var(--paper);
            color: var(--ink);
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .lsg-tab-btn.active {
            background: var(--primary-gradient);
            color: #fff;
            border-color: transparent;
            box-shadow: 0 6px 18px rgba(8, 97, 195, 0.28);
        }

        .lsg-panel {
            display: none;
        }
        .lsg-panel.active {
            display: block;
        }

        /* Danh sách thẻ lịch sử */
        .lsg-list {
            display: grid;
            gap: 14px;
        }
        .lsg-item {
            padding: 20px 22px;
            background: var(--glass);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: var(--shadow);
            -webkit-backdrop-filter: blur(24px) saturate(135%);
            backdrop-filter: blur(24px) saturate(135%);
        }
        .lsg-item-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        .lsg-title {
            font-size: 17px;
            font-weight: 800;
            color: var(--ink);
            margin: 0 0 4px;
        }
        .lsg-meta {
            font-size: 12.5px;
            color: var(--muted);
        }
        .lsg-body {
            font-size: 14.5px;
            line-height: 1.6;
            color: var(--ink);
            white-space: pre-wrap;
            word-break: break-word;
        }

        /* Nhãn trạng thái */
        .lsg-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }
        .status-new {
            background: rgba(239, 68, 68, 0.16);
            color: #dc2626;
        }
        .status-warn {
            background: rgba(245, 158, 11, 0.18);
            color: #b45309;
        }
        .status-info {
            background: rgba(59, 130, 246, 0.16);
            color: #1d4ed8;
        }
        .status-ok {
            background: rgba(16, 185, 129, 0.16);
            color: #047857;
        }
        html[data-glassy-theme="dark"] .status-new { color: #fca5a5; }
        html[data-glassy-theme="dark"] .status-warn { color: #fcd34d; }
        html[data-glassy-theme="dark"] .status-info { color: #93c5fd; }
        html[data-glassy-theme="dark"] .status-ok { color: #6ee7b7; }

        .lsg-empty {
            text-align: center;
            padding: 40px 20px;
            background: var(--glass);
            border: 1px solid var(--border);
            border-radius: 20px;
            color: var(--muted);
        }
    </style>
</head>
<body class="glassy-page">
<?php $glassyBase = '../'; require __DIR__ . '/../layout/glassy-sidebar.php'; ?>

<div id="glassy-content" class="glassy-content" tabindex="-1">
    <div class="lsg-wrap">
        <div class="lsg-header">
            <div>
                <h1>Lịch sử tư vấn &amp; phản hồi</h1>
                <p>Theo dõi trạng thái xử lý các yêu cầu bạn đã gửi lên hệ thống Kaido.</p>
            </div>
            <div class="lsg-quick-links">
                <a href="../tu-van/tuvan.php">+ Gửi yêu cầu tư vấn</a>
                <a href="../phan-hoi/phanhoi.php" class="secondary">+ Gửi phản hồi hệ thống</a>
                <a href="taikhoan.php" class="secondary">← Tài khoản</a>
            </div>
        </div>

        <?php if ($dbError): ?>
            <div class="kaido-msg kaido-bad"><?= lsg_h($dbError) ?></div>
        <?php else: ?>
            <div class="lsg-tabs" role="tablist">
                <button type="button" class="lsg-tab-btn active" onclick="switchLsgTab('tuvan', this)">
                    Yêu cầu tư vấn (<?= count($dsTuVan) ?>)
                </button>
                <button type="button" class="lsg-tab-btn" onclick="switchLsgTab('phanhoi', this)">
                    Phản hồi hệ thống (<?= count($dsPhanHoi) ?>)
                </button>
            </div>

            <!-- TAB 1: LỊCH SỬ YÊU CẦU TƯ VẤN -->
            <section id="panel-tuvan" class="lsg-panel active">
                <?php if (!$dsTuVan): ?>
                    <div class="lsg-empty">
                        <p>Bạn chưa gửi yêu cầu tư vấn nào.</p>
                    </div>
                <?php else: ?>
                    <div class="lsg-list">
                        <?php foreach ($dsTuVan as $item):
                            $stInfo = $tvStates[$item['status']] ?? ['Đang xử lý', 'status-info'];
                        ?>
                            <article class="lsg-item">
                                <div class="lsg-item-top">
                                    <div>
                                        <h2 class="lsg-title"><?= lsg_h($item['subject']) ?></h2>
                                        <div class="lsg-meta">
                                            Gửi lúc <?= lsg_h(date('d/m/Y H:i', strtotime($item['created_at']))) ?>
                                            • SĐT liên hệ: <strong><?= lsg_h($item['phone']) ?></strong>
                                        </div>
                                    </div>
                                    <span class="lsg-badge <?= lsg_h($stInfo[1]) ?>"><?= lsg_h($stInfo[0]) ?></span>
                                </div>
                                <div class="lsg-body"><?= lsg_h($item['message']) ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- TAB 2: LỊCH SỬ PHẢN HỒI HỆ THỐNG -->
            <section id="panel-phanhoi" class="lsg-panel">
                <?php if (!$dsPhanHoi): ?>
                    <div class="lsg-empty">
                        <p>Bạn chưa gửi phản hồi hệ thống nào.</p>
                    </div>
                <?php else: ?>
                    <div class="lsg-list">
                        <?php foreach ($dsPhanHoi as $item):
                            $stInfo = $phStates[$item['status']] ?? ['Đang xử lý', 'status-info'];
                            $catName = $phCategories[$item['category']] ?? $item['category'];
                        ?>
                            <article class="lsg-item">
                                <div class="lsg-item-top">
                                    <div>
                                        <h2 class="lsg-title"><?= lsg_h($catName) ?></h2>
                                        <div class="lsg-meta">
                                            Gửi lúc <?= lsg_h(date('d/m/Y H:i', strtotime($item['created_at']))) ?>
                                            • Email: <strong><?= lsg_h($item['email']) ?></strong>
                                        </div>
                                    </div>
                                    <span class="lsg-badge <?= lsg_h($stInfo[1]) ?>"><?= lsg_h($stInfo[0]) ?></span>
                                </div>
                                <div class="lsg-body"><?= lsg_h($item['message']) ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>

<script>
    function switchLsgTab(tabName, btn) {
        document.querySelectorAll('.lsg-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.lsg-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('panel-' + tabName).classList.add('active');
    }
</script>
</body>
</html>