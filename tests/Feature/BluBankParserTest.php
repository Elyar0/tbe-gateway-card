<?php

declare(strict_types=1);

use TelegramBotEssentials\GatewayCard\Services\SmsParsers\BluBankParser;

it('extracts the deposit amount from a real Blu Bank SMS', function () {
    $sms = "بلو\nواریز پول\nائلیار عزیز، 3,650,000 ریال به حساب شما نشست.\nموجودی: 4,032,891 ریال\n۱۱:۴۶\n۱۴۰۵.۰۶.۳۰";

    expect((new BluBankParser)->parseDepositAmount($sms))->toBe('3650000');
});

it('ignores an SMS with no deposit marker', function () {
    $sms = "بلو\nپیامک تبلیغاتی\nچیزی نامرتبط با واریز.";

    expect((new BluBankParser)->parseDepositAmount($sms))->toBeNull();
});

it('ignores an OTP message from the same-looking sender', function () {
    expect((new BluBankParser)->parseDepositAmount('کد تایید شما: 123456'))->toBeNull();
});

it('is unfazed by bidi/zero-width control characters', function () {
    $sms = "\u{200F}بلو\nواریز پول\nائلیار عزیز، \u{200E}1,000 ریال به حساب شما نشست.\nموجودی: 1,000 ریال";

    expect((new BluBankParser)->parseDepositAmount($sms))->toBe('1000');
});
