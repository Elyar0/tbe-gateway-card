<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Telegram\Bot\Objects\Update;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Essence\Models\Bot;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Models\MessageMeta;
use TelegramBotEssentials\Essence\Models\TelegramUser;
use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;
use TelegramBotEssentials\GatewayCard\Models\UnmatchedCardSms;
use TelegramBotEssentials\GatewayCard\Services\CardSmsMatcher;
use TelegramBotEssentials\Settings\Services\Settings;

function telegramCalled(string $method): bool
{
    return Http::recorded(fn ($request) => str_ends_with((string) $request->url(), '/'.$method))->isNotEmpty();
}

function fakeTelegram(): void
{
    // The base TestCase already faked outbound calls with an empty 200; a
    // real 'ok' body keeps the SDK from treating every response as a
    // failure and triggering exceptionReport()'s own extra HTTP call.
    // Stubs registered first win, so a fresh factory is needed rather than
    // calling Http::fake() again.
    $factory = new Factory;
    $factory->fake(['*' => Http::response(['ok' => true, 'result' => true])]);
    Http::swap($factory);
}

function configureCardSettings(Bot $bot, string $bank = 'blu_bank', string $currency = 'IRT'): void
{
    wHook()->setBot($bot);
    $settings = app(Settings::class);
    $settings->set('billing.gateways.card.status', true);
    $settings->set('billing.gateways.card.card_number', '6037-9900-0000-0000');
    $settings->set('billing.gateways.card.card_name', 'Jane Doe');
    $settings->set('billing.gateways.card.transactions_chat_id', '-1001234567890');
    $settings->set('billing.gateways.card.sms_bank', $bank);
    $settings->set('billing.currency', $currency);
}

/** A real Blu Bank deposit SMS, amount in Rial (Toman amount * 10). */
function bluBankSms(string $rialAmount): string
{
    $formatted = number_format((int) $rialAmount);

    return "بلو\nواریز پول\nائلیار عزیز، {$formatted} ریال به حساب شما نشست.\nموجودی: 4,032,891 ریال\n۱۱:۴۶\n۱۴۰۵.۰۶.۳۰";
}

function makePendingAttempt(Bot $bot, string $amountInTenantCurrency, ?Carbon $receivedAt): ToCardAttempt
{
    $peerId = random_int(100000, 999999999);
    TelegramUser::factory()->create(['peer_id' => $peerId]);
    $botUser = BotUser::factory()->create([
        'bot_id' => $bot->id,
        'telegram_user_peer_id' => $peerId,
    ]);

    // Invoice::factory() depends on TelegramBotEssentials\Essence\Database\
    // factories\InvoiceFactory, which doesn't exist in the currently
    // installed essence version (pre-existing, unrelated to this feature) -
    // build the row directly instead.
    $invoice = Invoice::create([
        'bot_id' => $bot->id,
        'bot_user_id' => $botUser->id,
        'price' => $amountInTenantCurrency,
        // A real, resolvable morph target - anything touching
        // $invoice->payable (offer summaries, notifications) needs an
        // actual model class, not an arbitrary string. Invoice::booted()
        // also soft-deletes any prior invoice sharing the same payable, so
        // this points at the BotUser just created above, which is unique
        // per call.
        'payable_type' => BotUser::class,
        'payable_id' => $botUser->id,
    ]);

    $attempt = ToCardAttempt::create([
        'card_number' => settings()->get('billing.gateways.card.card_number'),
        'amount' => $amountInTenantCurrency,
        'received_at' => $receivedAt,
    ]);

    billing()->attemptPayment($invoice, $attempt);

    $attempt->messageMeta()->save(new MessageMeta([
        'chat_id' => -1001234567890,
        'message_id' => random_int(1, 999999),
        'message_text' => 'New to card payment',
    ]));

    return $attempt;
}

beforeEach(function () {
    fakeTelegram();
});

it('auto-accepts the single unambiguous match', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);

    $attempt = makePendingAttempt($bot, '365000', now());

    app(CardSmsMatcher::class)->handle($bot, bluBankSms('3650000'));

    expect($attempt->fresh()->status)->toBe('succeed')
        ->and((string) $attempt->fresh()->received_amount)->toBe('365000')
        ->and($attempt->invoice->fresh()->status)->toBe('paid');

    $this->assertTelegramSent(fn ($request) => str_ends_with((string) $request->url(), '/editMessageReplyMarkup'));
});

it('leaves the payment pending and notifies the admin chat when nothing matches', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);

    makePendingAttempt($bot, '365000', now());

    app(CardSmsMatcher::class)->handle($bot, bluBankSms('9999999'));

    expect(ToCardAttempt::whereNull('status')->count())->toBe(1);
    $this->assertTelegramSent(fn ($request) => str_ends_with((string) $request->url(), '/sendMessage')
        && $request['chat_id'] === '-1001234567890');
    expect(telegramCalled('editMessageReplyMarkup'))->toBeFalse();
});

it('leaves the payment pending and notifies the admin chat on an ambiguous match', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);

    $first = makePendingAttempt($bot, '365000', now());
    $second = makePendingAttempt($bot, '365000', now());

    app(CardSmsMatcher::class)->handle($bot, bluBankSms('3650000'));

    expect($first->fresh()->status)->toBeNull()
        ->and($second->fresh()->status)->toBeNull();
    $this->assertTelegramSent(fn ($request) => str_ends_with((string) $request->url(), '/sendMessage'));
});

it('ignores an SMS that is not a recognized deposit notification', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);

    makePendingAttempt($bot, '365000', now());

    app(CardSmsMatcher::class)->handle($bot, 'Your OTP code is 123456');

    expect(ToCardAttempt::whereNull('status')->count())->toBe(1);
    expect(telegramCalled('editMessageReplyMarkup'))->toBeFalse()
        ->and(telegramCalled('sendMessage'))->toBeFalse();
});

it('does not match an attempt whose proof has not been submitted yet', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);

    $attempt = makePendingAttempt($bot, '365000', null);

    app(CardSmsMatcher::class)->handle($bot, bluBankSms('3650000'));

    expect($attempt->fresh()->status)->toBeNull();
});

it('parks an unmatched SMS for later replay instead of losing it', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);

    makePendingAttempt($bot, '365000', null);

    app(CardSmsMatcher::class)->handle($bot, bluBankSms('3650000'));

    $sms = UnmatchedCardSms::sole();
    expect($sms->bot_id)->toBe($bot->id)
        ->and((string) $sms->amount)->toBe('365000');
});

it('auto-accepts once proof is submitted after the SMS already arrived', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);

    $attempt = makePendingAttempt($bot, '365000', null);

    // The bank SMS beats the member back to the bot - handle() can't match
    // anything yet (no attempt has proof), so it parks the SMS.
    app(CardSmsMatcher::class)->handle($bot, bluBankSms('3650000'));
    expect($attempt->fresh()->status)->toBeNull();

    // The member now submits proof.
    $attempt->received_at = now();
    $attempt->save();

    app(CardSmsMatcher::class)->matchPendingSms($attempt);

    expect($attempt->fresh()->status)->toBe('succeed')
        ->and((string) $attempt->fresh()->received_amount)->toBe('365000')
        ->and(UnmatchedCardSms::count())->toBe(0);
});

it('does not clobber the caller\'s own webhook context when replay-matching', function () {
    // Regression: matchPendingSms() is called from inside the member's own
    // live update (proof submission) - autoAccept() must not blindly
    // reapply a WebhookContext there. Doing so calls wHook()->clear() then
    // reimports from $invoice->botUser, which - since payToCard() already
    // persisted state back to null before calling this - wipes both the
    // real incoming Update (replaced with an empty one) and requestState()
    // (the snapshot TelegramWebhookController fires BotStateAnswerHandled
    // with) out from under the request still running above this call.
    $bot = $this->makeBot();
    configureCardSettings($bot);

    $attempt = makePendingAttempt($bot, '365000', now());

    UnmatchedCardSms::create([
        'bot_id' => $bot->id,
        'amount' => '365000',
        'raw_text' => bluBankSms('3650000'),
        'received_at' => now(),
    ]);

    $liveUpdate = new Update(['update_id' => 999999, 'message' => ['message_id' => 1, 'text' => 'proof']]);
    wHook()->setBot($bot);
    wHook()->setUser($attempt->invoice->botUser);
    wHook()->setApi(telegramApi($bot->bot_token));
    wHook()->setUpdate($liveUpdate);

    app(CardSmsMatcher::class)->matchPendingSms($attempt);

    expect($attempt->fresh()->status)->toBe('succeed')
        ->and(wHook()->update()->toArray())->toBe($liveUpdate->toArray());
});

it('does not replay-match an ambiguous set of parked SMS', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);

    $attempt = makePendingAttempt($bot, '365000', null);

    // Two unrelated deposits of the same amount arrive before proof exists.
    app(CardSmsMatcher::class)->handle($bot, bluBankSms('3650000'));
    app(CardSmsMatcher::class)->handle($bot, bluBankSms('3650000'));
    expect(UnmatchedCardSms::count())->toBe(2);

    $attempt->received_at = now();
    $attempt->save();

    app(CardSmsMatcher::class)->matchPendingSms($attempt);

    expect($attempt->fresh()->status)->toBeNull()
        ->and(UnmatchedCardSms::count())->toBe(2);
});

it('does not replay-match a parked SMS from a different bot', function () {
    $botA = $this->makeBot();
    $botB = $this->makeBot();
    configureCardSettings($botA);
    configureCardSettings($botB);

    app(CardSmsMatcher::class)->handle($botB, bluBankSms('3650000'));

    $attempt = makePendingAttempt($botA, '365000', now());
    app(CardSmsMatcher::class)->matchPendingSms($attempt);

    expect($attempt->fresh()->status)->toBeNull();
});

it('does not replay-match a parked SMS outside the matching window', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);

    UnmatchedCardSms::create([
        'bot_id' => $bot->id,
        'amount' => '365000',
        'raw_text' => bluBankSms('3650000'),
        'received_at' => now()->subMinutes(31),
    ]);

    $attempt = makePendingAttempt($bot, '365000', now());
    app(CardSmsMatcher::class)->matchPendingSms($attempt);

    expect($attempt->fresh()->status)->toBeNull();
});

it('does not match an attempt outside the matching window', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot);

    $attempt = makePendingAttempt($bot, '365000', now()->subMinutes(31));

    app(CardSmsMatcher::class)->handle($bot, bluBankSms('3650000'));

    expect($attempt->fresh()->status)->toBeNull();
});

it('refuses to match when the tenant currency is not Rial-based', function () {
    $bot = $this->makeBot();
    configureCardSettings($bot, currency: 'USD');

    $attempt = makePendingAttempt($bot, '365000', now());

    app(CardSmsMatcher::class)->handle($bot, bluBankSms('3650000'));

    expect($attempt->fresh()->status)->toBeNull();
    expect(telegramCalled('sendMessage'))->toBeFalse();
});

it('never matches a pending attempt belonging to a different tenant', function () {
    $botA = $this->makeBot();
    $botB = $this->makeBot();
    configureCardSettings($botA);
    configureCardSettings($botB);

    $otherTenantAttempt = makePendingAttempt($botB, '365000', now());

    app(CardSmsMatcher::class)->handle($botA, bluBankSms('3650000'));

    expect($otherTenantAttempt->fresh()->status)->toBeNull();
});
