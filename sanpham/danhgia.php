<?php
/**
 * danhgia.php - Form đánh giá 1 sản phẩm (sao + nhận xét + ảnh + video).
 * Link vào trang: danhgia.php?order_id=<mã đơn đã giao>&product_id=<mã sản phẩm>
 * Chỉ người đã mua và đơn hàng ở trạng thái 'completed' mới đánh giá được.
 * Đánh giá rồi vẫn vào lại để sửa được.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/danhgia_helper.php';

$uid       = rv_require_login();
$orderId   = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$productId = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);

// 1) Kiểm tra quyền: đơn của chính người này, đã giao thành công, có chứa sản phẩm
$stmt = $conn->prepare(
    "SELECT oi.product_name, p.image_path
       FROM orders o
       JOIN order_items oi ON oi.order_id = o.id
       LEFT JOIN products p ON p.id = oi.product_id
      WHERE o.id = ? AND o.user_id = ? AND o.status = 'completed' AND oi.product_id = ?
      LIMIT 1"
);
$stmt->bind_param('iii', $orderId, $uid, $productId);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    http_response_code(403);
    $rv_title = 'Đánh giá sản phẩm';
    require __DIR__ . '/danhgia_dau.php';
    echo '<div class="account-card rv-card"><div class="rv-msg err">Bạn chỉ có thể đánh giá sản phẩm sau khi đơn hàng đã được giao thành công.</div>'
       . '<p><a href="../donhang/donhang.php">← Về đơn hàng của tôi</a></p></div>';
    require __DIR__ . '/danhgia_cuoi.php';
    exit;
}

// 2) Đánh giá đã có (nếu có) và media của nó
$stmt = $conn->prepare('SELECT id, rating, comment FROM product_reviews WHERE user_id = ? AND product_id = ? LIMIT 1');
$stmt->bind_param('ii', $uid, $productId);
$stmt->execute();
$review = $stmt->get_result()->fetch_assoc();
$stmt->close();

$media = [];
if ($review) {
    $stmt = $conn->prepare('SELECT id, file_path, media_type FROM review_media WHERE review_id = ? ORDER BY id');
    $rid = (int)$review['id'];
    $stmt->bind_param('i', $rid);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $media[(int)$row['id']] = $row;
    }
    $stmt->close();
}

$err     = '';
$rating  = (int)($review['rating'] ?? 0);
$comment = (string)($review['comment'] ?? '');

// 3) Xử lý gửi form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating  = (int)($_POST['rating'] ?? 0);
    $comment = trim((string)($_POST['comment'] ?? ''));
    $len     = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';

    $remove = [];
    foreach ((array)($_POST['remove_media'] ?? []) as $mid) {
        if (isset($media[(int)$mid])) {
            $remove[(int)$mid] = true;
        }
    }
    $keptImages = $keptVideos = 0;
    foreach ($media as $mid => $m) {
        if (isset($remove[$mid])) {
            continue;
        }
        $m['media_type'] === 'video' ? $keptVideos++ : $keptImages++;
    }

    $newImages = rv_normalize_files($_FILES['images'] ?? null);
    $newVideos = rv_normalize_files($_FILES['video'] ?? null);
    $toSave    = []; // [tmp, ext, type]

    if (!rv_csrf_ok()) {
        $err = 'Phiên làm việc hết hạn, vui lòng tải lại trang và thử lại.';
    } elseif ($rating < 1 || $rating > 5) {
        $err = 'Vui lòng chọn số sao từ 1 đến 5.';
    } elseif ($len($comment) > RV_COMMENT_MAX) {
        $err = 'Nhận xét tối đa ' . RV_COMMENT_MAX . ' ký tự.';
    } elseif ($keptImages + count($newImages) > RV_MAX_IMAGES) {
        $err = 'Tối đa ' . RV_MAX_IMAGES . ' ảnh cho mỗi đánh giá.';
    } elseif ($keptVideos + count($newVideos) > 1) {
        $err = 'Mỗi đánh giá chỉ được đính kèm 1 video.';
    } else {
        foreach ([['image', $newImages], ['video', $newVideos]] as [$kind, $files]) {
            foreach ($files as $f) {
                if ($f['error'] !== UPLOAD_ERR_OK) {
                    $err = $f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE
                        ? 'File "' . $f['name'] . '" vượt giới hạn dung lượng của máy chủ.'
                        : 'Tải file "' . $f['name'] . '" lên thất bại.';
                    break 2;
                }
                [$ok, $ext, $msg] = rv_validate_upload($f['tmp_name'], $f['size'], $kind);
                if (!$ok) {
                    $err = '"' . $f['name'] . '": ' . $msg;
                    break 2;
                }
                $toSave[] = [$f['tmp_name'], $ext, $kind];
            }
        }
    }

    if ($err === '') {
        $dir = rv_upload_dir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            $err = 'Không tạo được thư mục lưu ảnh (image/uploads/reviews).';
        }
    }

    if ($err === '') {
        $saved = []; // [đường dẫn tương đối, loại]
        foreach ($toSave as [$tmp, $ext, $kind]) {
            $name = 'rv_' . bin2hex(random_bytes(12)) . '.' . $ext;
            if (!move_uploaded_file($tmp, $dir . $name)) {
                $err = 'Không lưu được file tải lên.';
                break;
            }
            $saved[] = ['reviews/' . $name, $kind];
        }

        if ($err === '') {
            $commentDb = $comment === '' ? null : $comment;
            $removeIds = array_keys($remove);
            $removePaths = [];
            try {
                $conn->begin_transaction();
                if ($review) {
                    $rid = (int)$review['id'];
                    // sửa đánh giá: đánh dấu "chưa đọc" để admin thấy bản cập nhật; không đổi status (admin đã ẩn thì vẫn ẩn)
                    $stmt = $conn->prepare('UPDATE product_reviews SET rating = ?, comment = ?, order_id = ?, is_read = 0 WHERE id = ? AND user_id = ?');
                    $stmt->bind_param('isiii', $rating, $commentDb, $orderId, $rid, $uid);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare('INSERT INTO product_reviews (product_id, user_id, order_id, rating, comment) VALUES (?, ?, ?, ?, ?)');
                    $stmt->bind_param('iiiis', $productId, $uid, $orderId, $rating, $commentDb);
                    $stmt->execute();
                    $rid = (int)$stmt->insert_id;
                    $stmt->close();
                }
                if ($removeIds) {
                    $stmt = $conn->prepare('DELETE FROM review_media WHERE id = ? AND review_id = ?');
                    foreach ($removeIds as $mid) {
                        $stmt->bind_param('ii', $mid, $rid);
                        $stmt->execute();
                        $removePaths[] = $media[$mid]['file_path'];
                    }
                    $stmt->close();
                }
                if ($saved) {
                    $stmt = $conn->prepare('INSERT INTO review_media (review_id, file_path, media_type) VALUES (?, ?, ?)');
                    foreach ($saved as [$path, $kind]) {
                        $stmt->bind_param('iss', $rid, $path, $kind);
                        $stmt->execute();
                    }
                    $stmt->close();
                }
                $conn->commit();
                foreach ($removePaths as $p) {
                    $full = rv_upload_dir() . basename($p);
                    if (is_file($full)) {
                        @unlink($full);
                    }
                }
                header('Location: danhgia.php?order_id=' . $orderId . '&product_id=' . $productId . '&ok=1');
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                foreach ($saved as [$path]) {
                    @unlink($dir . basename($path));
                }
                error_log('danhgia: ' . $e->getMessage());
                $err = 'Gửi đánh giá thất bại, vui lòng thử lại. (Đã chạy file danhgia.sql chưa?)';
            }
        } else {
            foreach ($saved as [$path]) {
                @unlink($dir . basename($path));
            }
        }
    }
}

$editing = (bool)$review;
$rv_title = $editing ? 'Sửa đánh giá' : 'Đánh giá sản phẩm';
$rv_sub   = 'Chia sẻ trải nghiệm của bạn để giúp người mua sau';
require __DIR__ . '/danhgia_dau.php';
?>
        <div class="account-card rv-card">
            <?php if (($_GET['ok'] ?? '') === '1'): ?><div class="rv-msg ok">Cảm ơn bạn! Đánh giá đã được gửi đến cửa hàng.</div><?php endif; ?>
            <?php if ($err !== ''): ?><div class="rv-msg err"><?= rv_h($err) ?></div><?php endif; ?>

            <div class="rv-product">
                <?php if (!empty($item['image_path'])): ?><img src="<?= rv_h(rv_product_img($item['image_path'])) ?>" alt=""><?php endif; ?>
                <div><strong><?= rv_h($item['product_name']) ?></strong><div class="rv-hint">Đơn hàng #<?= (int)$orderId ?> - đã giao thành công</div></div>
            </div>

            <form method="POST" enctype="multipart/form-data" autocomplete="off">
                <?= rv_csrf_field() ?>
                <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
                <input type="hidden" name="product_id" value="<?= (int)$productId ?>">

                <div class="input-group">
                    <span class="lbl" style="display:block;font-weight:700;font-size:14px;margin-bottom:6px">Bạn chấm mấy sao?</span>
                    <div class="rv-stars" role="radiogroup" aria-label="Số sao">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" id="st<?= $i ?>" value="<?= $i ?>" <?= $rating === $i ? 'checked' : '' ?> <?= $i === 5 ? 'required' : '' ?>>
                            <label for="st<?= $i ?>" title="<?= $i ?> sao">★</label>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="input-group">
                    <label class="lbl" for="comment">Nhận xét (không bắt buộc)</label>
                    <textarea id="comment" name="comment" maxlength="<?= RV_COMMENT_MAX ?>" placeholder="Sản phẩm dùng thế nào? Chất lượng, đóng gói, giao hàng..."><?= rv_h($comment) ?></textarea>
                </div>

                <?php if ($media): ?>
                <div class="input-group">
                    <span class="lbl" style="display:block;font-weight:700;font-size:14px;margin-bottom:6px">Ảnh / video đã gửi</span>
                    <div class="rv-media">
                        <?php foreach ($media as $mid => $m): ?>
                            <figure>
                                <?php if ($m['media_type'] === 'video'): ?>
                                    <video src="<?= rv_h(rv_media_url($m['file_path'])) ?>" muted controls preload="metadata"></video>
                                <?php else: ?>
                                    <img src="<?= rv_h(rv_media_url($m['file_path'])) ?>" alt="">
                                <?php endif; ?>
                                <label><input type="checkbox" name="remove_media[]" value="<?= (int)$mid ?>"> Xóa</label>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="input-group">
                    <label class="lbl" for="images">Thêm ảnh (không bắt buộc)</label>
                    <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
                    <div class="rv-hint">Tối đa <?= RV_MAX_IMAGES ?> ảnh, mỗi ảnh ≤ 3MB (JPG, PNG, WebP).</div>
                </div>
                <div class="input-group">
                    <label class="lbl" for="video">Thêm video (không bắt buộc)</label>
                    <input type="file" id="video" name="video" accept="video/mp4,video/webm">
                    <div class="rv-hint">1 video, ≤ 20MB (MP4 hoặc WebM).</div>
                </div>

                <button type="submit"><?= $editing ? 'Lưu đánh giá' : 'Gửi đánh giá' ?></button>
                <a class="rv-btn ghost" href="danhgia_cho.php" style="margin-left:8px">Để sau</a>
            </form>
        </div>
        <div class="account-footer">
            <a href="../donhang/donhang.php">← Đơn hàng của tôi</a>
            <a href="chitietsanpham.php?id=<?= (int)$productId ?>">Xem sản phẩm</a>
        </div>
<?php require __DIR__ . '/danhgia_cuoi.php'; ?>
