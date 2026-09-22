<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use TelegramBotEssentials\Essence\Models\Bot;
use TelegramBotEssentials\Settings\Services\Settings;

function postSms(Bot $bot, string $text, ?string $secret): TestResponse
{
    $payload = json_encode(['text' => $text]);
    $server = ['CONTENT_TYPE' => 'application/json'];

    if ($secret !== null) {
        $server['HTTP_X_SIGNATURE'] = hash_hmac('sha256', $payload, $secret);
    }

    return test()->call('POST', "/api/{$bot->unique_id}/gateway-card/sms", server: $server, content: $payload);
}

function configureWebhookSecret(Bot $bot, ?string $secret): void
{
    // SettingType::SENSITIVE encrypts at rest, which needs a real app key -
    // the testbench skeleton doesn't set one by default.
    if (! config('app.key')) {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    wHook()->setBot($bot);
    $settings = app(Settings::class);
    $settings->set('billing.gateways.card.status', true);
    $settings->set('billing.gateways.card.card_number', '6037-9900-0000-0000');
    $settings->set('billing.gateways.card.card_name', 'Jane Doe');
    $settings->set('billing.gateways.card.transactions_chat_id', '-1001234567890');
    $settings->set('billing.gateways.card.sms_bank', 'blu_bank');
    $settings->set('billing.currency', 'IRT');

    if ($secret !== null) {
        $settings->set('billing.gateways.card.sms_secret', $secret);
    }
}

it('accepts a request with a valid signature', function () {
    $bot = $this->makeBot();
    configureWebhookSecret($bot, 'my-secret');

    $response = postSms($bot, 'unrelated text', 'my-secret');

    $response->assertOk();
    expect($response->json('success'))->toBeTrue();
});

it('rejects a request with an invalid signature', function () {
    $bot = $this->makeBot();
    configureWebhookSecret($bot, 'my-secret');

    $response = postSms($bot, 'unrelated text', 'wrong-secret');

    $response->assertStatus(401);
});

it('rejects a request with no signature at all', function () {
    $bot = $this->makeBot();
    configureWebhookSecret($bot, 'my-secret');

    $response = postSms($bot, 'unrelated text', null);

    $response->assertStatus(401);
});

it('refuses when the card gateway is disabled', function () {
    $bot = $this->makeBot();
    configureWebhookSecret($bot, 'my-secret');
    wHook()->setBot($bot);
    app(Settings::class)->set('billing.gateways.card.status', false);

    $response = postSms($bot, 'unrelated text', 'my-secret');

    $response->assertStatus(404);
});

it('refuses when no SMS secret is configured', function () {
    $bot = $this->makeBot();
    configureWebhookSecret($bot, null);

    $response = postSms($bot, 'unrelated text', 'anything');

    $response->assertStatus(404);
});
