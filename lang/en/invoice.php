<?php

return [
    'to_card' => [
        'text' => [
            'admin-payment_result' => '#⃣ Invoice :invoiceId'
                ."\r\n🏷 New to card Payment"
                ."\r\n"
                ."\r\n👤 User: <a href=\"tg://user?id=:userPeerId\">:userFullName</a>"
                ."\r\n💲 Amount: :invoiceAmount"
                ."\r\n"
                ."\r\n📝 Order Description: \r\n:invoiceDescription"
                ."\r\n"
                ."\r\n📝 User payment description: \r\n:paymentDescription",
            'admin_payment_rejection' => '🔏 Rejecting Card Payment :toCardAttemptId'
                ."\r\n"
                ."\r\nEnter your rejection reason below:",
            'admin-payment_rejected' => '🛑 Reason sent & payment rejected successfully.',
            'user-payment_result' => 'Payment submitted successfully, wait for processing',
            'user-pay_message' => 'Pay the amount to this card then send the result here for processing'
                ."\r\n"
                ."\r\n🔸 <code>:cardNumber</code>"
                ."\r\n :cardName",
            'user-payment_rejected' => '❌ Your payment rejected due to reason below:'
                ."\r\n:rejectionReason",
        ],
        'answers' => [
            'admin-rejecting_payment' => 'initializing for rejection',
            'admin-payment_accepted' => 'Payment accepted',
            'attempting' => 'Initializing card payment...',
        ],
        'keys' => [
            'admin-accept_payment' => '✅ Accept Payment',
            'admin-reject_payment' => '❌ Reject Payment',
            'member-card_payment' => 'Card payment',
        ],
        'lock-keys' => [
            'admin-rejecting_payment' => 'Waiting for rejection answer',
            'admin-payment_accepted_by' => 'Payment accepted By :adminName',
            'admin-payment_rejected_by' => 'Payment rejected By :adminName',
            'user-payment_accepted' => 'Payment accepted',
            'user-payment_rejected' => 'Payment rejected',
            'user-waiting_for_payment' => 'Waiting for payment',
            'user-wait_for_payment_processing' => 'Waiting for payment processing',
        ],
        'labels' => [
            'gateway' => 'Card',
        ],
    ],
];
