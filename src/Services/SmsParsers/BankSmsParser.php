<?php

namespace TelegramBotEssentials\GatewayCard\Services\SmsParsers;

interface BankSmsParser
{
    /**
     * Returns the deposited Rial amount as a numeric string if $text is a
     * genuine deposit notification from this bank, or null otherwise (a
     * withdrawal notice, an OTP, or any other message shape).
     */
    public function parseDepositAmount(string $text): ?string;
}
