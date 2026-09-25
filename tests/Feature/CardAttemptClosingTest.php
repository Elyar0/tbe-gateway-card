<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use TelegramBotEssentials\Essence\Support\WebhookContext;
use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;
use TelegramBotEssentials\GatewayCard\Services\UniqueAmountResolver;

function applyMemberContext(ToCardAttempt $attempt): void
{
    $invoice = $attempt->invoice;

    (new WebhookContext(
        botId: $invoice->bot_id,
        botUserId: $invoice->bot_user_id,
        bot: $invoice->bot,
        botUser: $invoice->botUser,
    ))->apply();
}

beforeEach(function () {
    fakeTelegram();
});

it('closes an open attempt once and leaves an already-resolved one alone', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);
    $attempt = makePendingAttempt($bot, '365000', now());

    $attempt->close(ToCardAttempt::STATUS_CANCELLED);
    $attempt->close(ToCardAttempt::STATUS_SUPERSEDED);

    expect($attempt->fresh()->status)->toBe('cancelled');
});

it('stops counting a closed attempt as a competing payment', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);
    setUniqueAmountMode('soft');
    $attempt = makePendingAttempt($bot, '365000', now());

    expect(app(UniqueAmountResolver::class)->resolve('365000'))->toBe('1000');

    $attempt->close(ToCardAttempt::STATUS_SUPERSEDED);

    expect(app(UniqueAmountResolver::class)->resolve('365000'))->toBeNull();
});

it('closes the card attempt when the invoice is settled some other way', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);
    $attempt = makePendingAttempt($bot, '365000', now());

    applyMemberContext($attempt);
    $attempt->invoice->markAsPaid();

    expect($attempt->fresh()->status)->toBe('cancelled');
});

it('keeps the succeed status when the card attempt itself settles the invoice', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);
    $attempt = makePendingAttempt($bot, '365000', now());

    applyMemberContext($attempt);
    $attempt->attemptSucceed();

    expect($attempt->fresh()->status)->toBe('succeed');
});

it('backfills attempts that were left open before explicit closing existed', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);

    $settledElsewhere = makePendingAttempt($bot, '100000', now());
    DB::table('invoices')->where('id', $settledElsewhere->invoice->id)->update(['status' => 'paid']);

    $replaced = makePendingAttempt($bot, '200000', now());
    $replacement = ToCardAttempt::create(['card_number' => '6037990000000000', 'amount' => '200000']);
    billing()->attemptPayment($replaced->invoice, $replacement);

    $live = makePendingAttempt($bot, '300000', now());

    (require __DIR__.'/../../database/migrations/2026_09_25_000000_close_stale_to_card_attempts.php')->up();

    expect($settledElsewhere->fresh()->status)->toBe('cancelled')
        ->and($replaced->fresh()->status)->toBe('superseded')
        ->and($replacement->fresh()->status)->toBeNull()
        ->and($live->fresh()->status)->toBeNull();
});
