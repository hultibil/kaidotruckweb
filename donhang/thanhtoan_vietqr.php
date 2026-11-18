<?php
// Đặt tại: donhang/thanhtoan_vietqr.php
if (session_status() === PHP_SESSION_NONE) session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/trangthai.php';
$cfg = require __DIR__ . '/../vietqr_config.php';

if (empty($_SESSION['user_id'])) { header('Location: ../Taikhoan/dangnhap.php'); exit; }
$uid = (int)$_SESSION['user_id'];
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); exit('Mã đơn không hợp lệ.'); }

$st = $conn->prepare('SELECT id,total_amount,status,payment_method,payment_status FROM orders WHERE id=? AND user_id=?');
$st->bind_param('ii', $id, $uid); $st->execute();
$o = $st->get_result()->fetch_assoc(); $st->close();
if (!$o || $o['payment_method'] !== 'vietqr') { http_response_code(404); exit('Không tìm thấy đơn thanh toán VietQR.'); }

// Endpoint cho JS kiểm tra trạng thái
if (isset($_GET['check'])) {
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    echo json_encode(['paid' => $o['payment_status'] === 'paid', 'cancelled' => $o['status'] === 'cancelled']);
    exit;
}

$amount  = (int)round((float)$o['total_amount']);
$content = $cfg['prefix'] . str_pad((string)$o['id'], 6, '0', STR_PAD_LEFT);
$qrUrl = 'https://img.vietqr.io/image/' . rawurlencode($cfg['bank_id']) . '-' . rawurlencode($cfg['account_no']) . '-' . rawurlencode($cfg['template']) . '.png'
       . '?amount=' . $amount . '&addInfo=' . rawurlencode($content) . '&accountName=' . rawurlencode($cfg['account_name']);
$paid = $o['payment_status'] === 'paid';
$cancelled = $o['status'] === 'cancelled';
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Thanh toán VietQR - Kaido</title>
<link rel="stylesheet" href="../css/glassy.css"><script src="../js/glassy-theme.js"></script>
<style>
.vq{max-width:460px;margin:30px auto;padding:24px;border-radius:14px;background:rgba(30,41,59,.85);color:#f1f5f9;text-align:center}
body.light-mode .vq{background:#fff;color:#0f172a;border:1px solid #cbd5e1}
.vq img{width:100%;max-width:340px;background:#fff;border-radius:10px}
.vq dl{text-align:left;margin:16px 0}.vq dt{font-size:12px;opacity:.7;margin-top:10px}
.vq dd{margin:2px 0;font-weight:700;display:flex;justify-content:space-between;gap:8px}
.vq button{padding:4px 10px;border:0;border-radius:5px;cursor:pointer}
.vq .ok{color:#34d399;font-weight:700;font-size:18px}.vq .bad{color:#f87171;font-weight:700}
.vq a.btn{display:inline-block;margin-top:14px;padding:10px 18px;border-radius:8px;background:#21895b;color:#fff;text-decoration:none}
</style></head><body class="glassy-page">
<?php $glassyBase = '../'; require __DIR__ . '/../layout/glassy-sidebar.php'; ?>
<div id="glassy-content" class="glassy-content" tabindex="-1"><main class="vq">
<h1>Thanh toán VietQR</h1>
<p>Đơn hàng <strong><?= h(maDon((int)$o['id'])) ?></strong></p>
<?php if ($paid): ?>
  <p class="ok">✔ Đã nhận thanh toán. Cảm ơn bạn!</p>
<?php elseif ($cancelled): ?>
  <p class="bad">Đơn hàng đã bị hủy, không thể thanh toán.</p>
<?php else: ?>
  <img src="<?= h($qrUrl) ?>" alt="Mã QR thanh toán">
  <p>Mở app ngân hàng → quét mã QR. Số tiền và nội dung đã điền sẵn.</p>
  <dl>
    <dt>Ngân hàng</dt><dd><?= h($cfg['bank_id']) ?></dd>
    <dt>Số tài khoản</dt><dd><span id="v1"><?= h($cfg['account_no']) ?></span><button onclick="cp('v1')">Sao chép</button></dd>
    <dt>Chủ tài khoản</dt><dd><?= h($cfg['account_name']) ?></dd>
    <dt>Số tiền</dt><dd><span id="v2"><?= $amount ?></span><button onclick="cp('v2')">Sao chép</button></dd>
    <dt>Nội dung chuyển khoản (bắt buộc đúng)</dt><dd><span id="v3"><?= h($content) ?></span><button onclick="cp('v3')">Sao chép</button></dd>
  </dl>
  <p id="stt" style="opacity:.8">Đang chờ thanh toán… (trang tự cập nhật)</p>
<?php endif; ?>
<a class="btn" href="donhang.php">Xem đơn hàng của tôi</a>
</main></div>
<script>
function cp(id){navigator.clipboard&&navigator.clipboard.writeText(document.getElementById(id).textContent.trim());}
<?php if (!$paid && !$cancelled): ?>
setInterval(function(){
  fetch('thanhtoan_vietqr.php?id=<?= (int)$o['id'] ?>&check=1',{cache:'no-store'}).then(r=>r.json()).then(d=>{
    if(d.paid||d.cancelled) location.reload();
  }).catch(function(){});
},5000);
<?php endif; ?>
</script></body></html>
