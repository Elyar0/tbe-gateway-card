<?php

namespace TelegramBotEssentials\GatewayCard\Services\SmsParsers;

class BluBankParser implements BankSmsParser
{
    /**
     * Blu Bank deposit SMS shape (sender/name/greeting vary, this is stable):
     *
     *   بلو
     *   واریز پول
     *   ائلیار عزیز، 3,650,000 ریال به حساب شما نشست.
     *   موجودی: 4,032,891 ریال
     *   ۱۱:۴۶
     *   ۱۴۰۵.۰۶.۳۰
     *
     * There is no reference number or destination account suffix anywhere in
     * the message, so the amount is the only signal available to match on.
     */
    public function parseDepositAmount(string $text): ?string
    {
        // Strip bidi/zero-width control characters some clients inject when
        // copying Persian text, so the whitespace regex below still lines up.
        $normalized = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}]/u', '', $text) ?? $text;

        if (! str_contains($normalized, 'واریز پول')) {
            return null;
        }

        if (! preg_match('/([\d,]+)\s*ریال\s+به\s+حساب\s+شما\s+نشست/u', $normalized, $matches)) {
            return null;
        }

        return str_replace(',', '', $matches[1]);
    }
}
