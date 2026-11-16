<?php
/** Khung đầu trang đánh giá. Trước khi require, trang phải đặt $rv_title (và tùy chọn $rv_sub). Phải require ở phạm vi toàn cục. */
$glassyBase = '../';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= rv_h($rv_title) ?></title>
    <link rel="icon" href="../image/kaidoLogomini.png">
    <link rel="stylesheet" href="../css/dndk.css">
    <link rel="stylesheet" href="../css/glassy.css">
    <script src="../js/glassy-theme.js"></script>
    <style>
        .glassy-page .glassy-content .rv-card{max-width:700px;margin:0 auto 18px;padding:22px;text-align:left}
        .rv-card .input-group{margin-bottom:14px}
        .rv-card label.lbl{display:block;margin-bottom:6px;font-weight:700;font-size:14px}
        .rv-card textarea,.rv-card input[type=file]{width:100%;box-sizing:border-box;padding:11px 13px;border:1px solid var(--field-border);border-radius:12px;font:inherit;background:var(--paper);color:var(--ink)}
        .rv-card textarea{min-height:110px;resize:vertical}
        .rv-hint{font-size:12px;margin-top:5px;color:var(--muted)!important}
        .rv-card button[type="submit"],.rv-btn{display:inline-block;padding:11px 20px;border:0;border-radius:12px;font:inherit;font-weight:700;cursor:pointer;color:#fff!important;background:var(--primary-gradient)!important;text-decoration:none}
        .rv-btn.ghost{background:var(--paper)!important;color:var(--accent)!important;border:1px solid var(--field-border)}
        .rv-card a{color:var(--accent);font-weight:600}
        .rv-msg{padding:10px 14px;border-radius:12px;margin-bottom:14px;font-size:14px;font-weight:600}
        .rv-msg.err{background:rgba(239,68,68,.14);border:1px solid rgba(239,68,68,.45);color:var(--danger)!important}
        .rv-msg.ok{background:rgba(16,185,129,.14);border:1px solid rgba(16,185,129,.45);color:var(--success)!important}
        .rv-product{display:flex;gap:14px;align-items:center;margin-bottom:18px}
        .rv-product img{width:72px;height:72px;object-fit:cover;border-radius:12px;border:1px solid var(--border)}
        .rv-stars{display:inline-flex;flex-direction:row-reverse;justify-content:flex-end;gap:2px}
        .rv-stars input{position:absolute;opacity:0;pointer-events:none}
        html body.glassy-page .glassy-content .rv-stars label{font-size:38px;line-height:1;cursor:pointer;color:#9db4cc!important;transition:color .15s}
        html body.glassy-page .glassy-content .rv-stars input:checked~label,
        html body.glassy-page .glassy-content .rv-stars label:hover,
        html body.glassy-page .glassy-content .rv-stars label:hover~label{color:#f59e0b!important}
        .rv-stars input:focus-visible+label{outline:2px solid var(--accent);border-radius:6px}
        .rv-media{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:8px}
        .rv-media figure{margin:0;width:104px;text-align:center;font-size:12px}
        .rv-media img,.rv-media video{width:104px;height:104px;object-fit:cover;border-radius:10px;border:1px solid var(--border);background:#000}
        .rv-row{display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:12px 0;border-bottom:1px solid var(--border)}
        .rv-row:last-child{border-bottom:0}
        .rv-row .info{display:flex;gap:12px;align-items:center;min-width:0}
        .rv-row img{width:56px;height:56px;object-fit:cover;border-radius:10px}
        html body.glassy-page .glassy-content .rv-star-on{color:#f59e0b!important;letter-spacing:2px}
        .glassy-page .account-container .account-footer{display:flex;flex-wrap:wrap;gap:8px 22px;justify-content:center}
        .glassy-page .account-container .account-footer a{color:var(--accent)}
    </style>
</head>
<body class="glassy-page account-page">
<?php require __DIR__ . '/../layout/glassy-sidebar.php'; ?>
<div id="glassy-content" class="glassy-content" tabindex="-1">
    <div class="header" id="mainHeader">
        <div class="header-left">
            <img class="logo" src="../image/kaidologo.jpg" alt="">
            <h2><?= rv_h($rv_title) ?></h2>
        </div>
        <div class="header-right">
            <div class="user-header-badge"><span>👋 Hi, <strong><?= rv_h($_SESSION['username'] ?? '') ?></strong></span></div>
        </div>
    </div>
    <div class="account-container">
        <div class="account-header">
            <h1><?= rv_h($rv_title) ?></h1>
            <?php if (!empty($rv_sub)): ?><p><?= rv_h($rv_sub) ?></p><?php endif; ?>
        </div>
