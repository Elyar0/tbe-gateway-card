<?php

declare(strict_types=1);

use TelegramBotEssentials\Billing\Services\Gateways;
use TelegramBotEssentials\Settings\Services\Settings;

it('registers the card gateway settings under the billing tree', function () {
    $keys = app(Settings::class)->getSettings()->keys();

    expect($keys)->toContain(
        'billing.gateways.card',
        'billing.gateways.card.status',
        'billing.gateways.card.card_number',
        'billing.gateways.card.card_name',
        'billing.gateways.card.transactions_chat_id',
    );
});

it('exposes a card payment gateway to billing', function () {
    $keys = app(Gateways::class)->getGateways()->pluck('key');

    expect($keys)->toContain('card');
});

it('shows the admin the exact webhook URL for their own bot in the SMS secret setting', function () {
    $bot = $this->makeBot();
    wHook()->setBot($bot);

    $description = app(Settings::class)->getSetting('billing.gateways.card.sms_secret')->getDescription();

    expect($description)->toContain("/api/{$bot->unique_id}/gateway-card/sms");
});
