<?php

function taoDonHang(mysqli $conn, int $userId, string $ten, string $sdt, string $diaChi, array $gioHang): int
{
    if (!$gioHang) throw new RuntimeException('Giỏ hàng trống.');
    $conn->begin_transaction();
    try {
        $danhSach = [];
        $tong = 0.0;
        $khoa = array_keys($gioHang);
        sort($khoa, SORT_NUMERIC);
        $laySP = $conn->prepare('SELECT id, product_name, price, stock_qty, status FROM products WHERE id=? FOR UPDATE');
        foreach ($khoa as $maSP) {
            $idSP = filter_var($maSP, FILTER_VALIDATE_INT);
            $soLuong = filter_var($gioHang[$maSP], FILTER_VALIDATE_INT);
            if ($idSP === false || $idSP <= 0 || $soLuong === false || $soLuong <= 0) {
                throw new RuntimeException('Dữ liệu giỏ hàng không hợp lệ.');
            }
            $laySP->bind_param('i', $idSP);
            $laySP->execute();
            $sp = $laySP->get_result()->fetch_assoc();
            if (!$sp || $sp['status'] !== 'active') {
                throw new RuntimeException('Một sản phẩm không còn được bán.');
            }
            if ((int)$sp['stock_qty'] < $soLuong) {
                throw new RuntimeException('Không đủ tồn kho cho sản phẩm: ' . $sp['product_name']);
            }
            $gia = (float)$sp['price'];
            if ($gia < 0) throw new RuntimeException('Giá sản phẩm không hợp lệ.');
            $danhSach[] = [
                'id' => $idSP, 'ten' => $sp['product_name'],
                'gia' => $gia, 'so_luong' => $soLuong
            ];
            $tong += $gia * $soLuong;
        }
        if (!$danhSach) throw new RuntimeException('Giỏ hàng trống.');

        // Bảng orders: recipient_name, phone, shipping_address, total_amount, status, payment_method
        $themDon = $conn->prepare(
            'INSERT INTO orders (user_id,recipient_name,phone,shipping_address,total_amount,status,payment_method)
             VALUES (?,?,?,?,?,?,?)'
        );
        $trangThai = 'pending';
        $thanhToan = 'cod';
        $themDon->bind_param('isssdss', $userId, $ten, $sdt, $diaChi, $tong, $trangThai, $thanhToan);
        $themDon->execute();
        $donId = (int)$conn->insert_id;

        // Bảng order_items: order_id, product_id, product_name, unit_price, quantity
        $themCT = $conn->prepare(
            'INSERT INTO order_items (order_id,product_id,product_name,unit_price,quantity)
             VALUES (?,?,?,?,?)'
        );
        $truKho = $conn->prepare('UPDATE products SET stock_qty=stock_qty-? WHERE id=? AND stock_qty>=?');
        // Không đổi products.status khi hết hàng: cột này chỉ có 'active'/'inactive',
        // và shop.php/giỏ hàng đã tự kiểm tra stock_qty > 0.
        foreach ($danhSach as $sp) {
            $idSP = $sp['id']; $tenSP = $sp['ten']; $gia = $sp['gia']; $sl = $sp['so_luong'];
            $themCT->bind_param('iisdi', $donId, $idSP, $tenSP, $gia, $sl);
            $themCT->execute();
            $truKho->bind_param('iii', $sl, $idSP, $sl);
            $truKho->execute();
            if ($truKho->affected_rows !== 1) throw new RuntimeException('Không thể trừ số lượng tồn kho.');
        }

        // Bảng order_status_history: order_id, status, note
        $ghiChu = 'Đặt hàng thành công, chờ cửa hàng xác nhận.';
        $themMoc = $conn->prepare('INSERT INTO order_status_history (order_id,status,note) VALUES (?,?,?)');
        $themMoc->bind_param('iss', $donId, $trangThai, $ghiChu);
        $themMoc->execute();
        $conn->commit();
        return $donId;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
