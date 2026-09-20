<?php

return [
    'to_card' => [
        'text' => [
            'admin-payment_result' => '#⃣ فاکتور :invoiceId'
                ."\r\n🏷 پرداخت جدید به کارت"
                ."\r\n"
                ."\r\n👤 کاربر: <a href=\"tg://user?id=:userPeerId\">:userFullName</a>"
                ."\r\n💲 مبلغ: :invoiceAmount"
                ."\r\n"
                ."\r\n📝 توضیحات سفارش: \r\n:invoiceDescription"
                ."\r\n"
                ."\r\n📝 توضیحات پرداخت کاربر: \r\n:paymentDescription",
            'admin_payment_rejection' => '🔏 رد پرداخت کارت :toCardAttemptId'
                ."\r\n"
                ."\r\nدلیل رد پرداخت را وارد کنید:",
            'admin-payment_rejected' => '🛑 دلیل ارسال شد و پرداخت با موفقیت رد شد.',
            'user-payment_result' => '✅ پرداخت با موفقیت ثبت شد، منتظر پردازش بمانید',
            'user-pay_message' => 'مبلغ را به کارت زیر واریز کرده و نتیجه را ارسال کنید:'
                ."\r\n"
                ."\r\n🔸 <code>:cardNumber</code>"
                ."\r\n :cardName",
            'user-payment_rejected' => '❌ پرداخت شما به دلیل زیر رد شد:'
                ."\r\n:rejectionReason",
        ],
        'answers' => [
            'admin-rejecting_payment' => 'در حال آماده‌سازی برای رد پرداخت',
            'admin-payment_accepted' => 'پرداخت تأیید شد',
            'attempting' => '⏳ در حال شروع پرداخت به کارت...',
        ],
        'keys' => [
            'admin-accept_payment' => '✅ تایید پرداخت',
            'admin-reject_payment' => '❌ رد پرداخت',
            'member-card_payment' => 'پرداخت کارت 💳',
        ],
        'lock-keys' => [
            'admin-rejecting_payment' => 'در انتظار پاسخ رد پرداخت',
            'admin-payment_accepted_by' => 'پرداخت تایید شد توسط :adminName',
            'admin-payment_rejected_by' => 'پرداخت رد شد توسط :adminName',
            'user-payment_accepted' => 'پرداخت تایید شد',
            'user-payment_rejected' => 'پرداخت رد شد',
            'user-waiting_for_payment' => 'در انتظار پرداخت کاربر',
            'user-wait_for_payment_processing' => 'در انتظار پردازش پرداخت',
        ],
        'labels' => [
            'gateway' => 'کارت',
        ],
    ],
];
