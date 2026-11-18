<?php
// Đặt tại: vietqr_config.php (thư mục gốc web, cùng cấp db.php)
return [
    'bank_id'        => 'VCB',            // Mã ngân hàng theo vietqr.io: VCB, MB, TCB, ACB, BIDV, VPB, TPB... (hoặc số BIN)
    'account_no'     => 'SO_TAI_KHOAN',     // SỐ TÀI KHOẢN CỦA SHOP
    'account_name'   => 'TEN CHU TAI KHOAN',   // TÊN CHỦ TK (viết hoa, không dấu)
    'template'       => 'compact2',       // compact | compact2 | qr_only | print
    'prefix'         => 'KD',             // nội dung CK = KD + mã đơn 6 số = đúng mã đơn maDon(), vd KD000006
    'sepay_api_key'  => '',               // để trống nếu chưa dùng webhook tự động (SePay)
];
