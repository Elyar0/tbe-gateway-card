<?php

namespace TelegramBotEssentials\GatewayCard\Listeners;

use TelegramBotEssentials\Billing\Events\InvoiceStatusEvent;
use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;

class CloseCardAttemptOnInvoiceSettled
{
    /**
     * A card attempt closes itself when it is the one that settles the
     * invoice; if the invoice was settled some other way (wallet, another
     * gateway) the card attempt is left open, and would keep looking like a
     * live payment competing for its amount.
     */
    public function handle(InvoiceStatusEvent $event): void
    {
        $attempt = $event->invoice->paymentAttempt;

        if ($attempt instanceof ToCardAttempt) {
            $attempt->close(ToCardAttempt::STATUS_CANCELLED);
        }
    }
}
