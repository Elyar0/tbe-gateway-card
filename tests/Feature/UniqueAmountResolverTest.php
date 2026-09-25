<?php

declare(strict_types=1);

use TelegramBotEssentials\GatewayCard\Services\UniqueAmountResolver;
use TelegramBotEssentials\Settings\Models\BotSetting;
use TelegramBotEssentials\Settings\Services\Settings;

function setUniqueAmountMode(string $mode): void
{
    app(Settings::class)->set('billing.gateways.card.unique_amount', $mode);
}

beforeEach(function () {
    fakeTelegram();
});

it('never adds an offset when disabled', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);
    setUniqueAmountMode('disabled');
    makePendingAttempt($bot, '365000', now());

    expect(app(UniqueAmountResolver::class)->resolve('365000'))->toBeNull();
});

it('always adds an offset when hard, even with nothing pending', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);
    setUniqueAmountMode('hard');

    expect(app(UniqueAmountResolver::class)->resolve('365000'))->toBe('1000');
});

it('adds no offset when soft and nothing else is pending at that amount', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);
    setUniqueAmountMode('soft');
    makePendingAttempt($bot, '400000', now());

    expect(app(UniqueAmountResolver::class)->resolve('365000'))->toBeNull();
});

it('adds an offset when soft and another attempt is pending at the same amount', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);
    setUniqueAmountMode('soft');
    makePendingAttempt($bot, '365000', now());

    expect(app(UniqueAmountResolver::class)->resolve('365000'))->toBe('1000');
});

it('skips offsets already taken and drops to a finer step once the coarse one is exhausted', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);
    setUniqueAmountMode('hard');

    foreach ([1000, 2000, 3000, 4000, 5000] as $taken) {
        makePendingAttempt($bot, (string) (365000 + $taken), now());
    }

    expect(app(UniqueAmountResolver::class)->resolve('365000'))->toBe('500');
});

it('ignores pending attempts that belong to a different tenant', function () {
    $bot = $this->makeBot();
    $otherBot = $this->makeBot();
    configureCardSettings($otherBot);
    makePendingAttempt($otherBot, '365000', now());

    configureCardSettings($bot);
    setUniqueAmountMode('soft');

    expect(app(UniqueAmountResolver::class)->resolve('365000'))->toBeNull();
});

it('reads a legacy stored true as hard and false as disabled', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);
    $resolver = app(UniqueAmountResolver::class);

    BotSetting::updateOrCreate(
        ['bot_id' => $bot->id, 'key' => 'billing.gateways.card.unique_amount'],
        ['value' => '1']
    );
    app('cache')->flush();
    expect($resolver->resolve('365000'))->toBe('1000');

    BotSetting::where('bot_id', $bot->id)->where('key', 'billing.gateways.card.unique_amount')->update(['value' => '0']);
    app('cache')->flush();
    expect($resolver->resolve('365000'))->toBeNull();
});

it('cannot credit a wallet when the user-wallet package is not installed', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);

    expect(app(UniqueAmountResolver::class)->canCreditWallet())->toBeFalse();
});
