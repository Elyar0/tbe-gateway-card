<?php

declare(strict_types=1);

namespace TelegramBotEssentials\GatewayCard\Telegram\CallbackQueries\Member {
    // Overrides the built-in random_int() for this class only: an
    // unqualified call inside a namespace resolves to a function of the
    // same name in that namespace before falling back to the global one.
    // Lets the "collides, then retries" test drive the offsets
    // deterministically instead of hoping a real random draw reproduces a
    // collision.
    function random_int(int $min, int $max): int
    {
        return array_shift(\CardPaymentUniqueAmountTestState::$queue) ?? \random_int($min, $max);
    }
}

namespace {
    use TelegramBotEssentials\Billing\Models\Invoice;
    use TelegramBotEssentials\Essence\Models\Bot;
    use TelegramBotEssentials\Essence\Models\BotUser;
    use TelegramBotEssentials\Essence\Models\TelegramUser;
    use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;
    use TelegramBotEssentials\GatewayCard\Telegram\CallbackQueries\Member\CardPaymentQuery;

    class CardPaymentUniqueAmountTestState
    {
        /** @var int[] */
        public static array $queue = [];
    }

    function makeInvoiceForUniqueAmountTest(Bot $bot, string $price): Invoice
    {
        $peerId = random_int(100000, 999999999);
        TelegramUser::factory()->create(['peer_id' => $peerId]);
        $botUser = BotUser::factory()->create([
            'bot_id' => $bot->id,
            'telegram_user_peer_id' => $peerId,
        ]);

        return Invoice::create([
            'bot_id' => $bot->id,
            'bot_user_id' => $botUser->id,
            'price' => $price,
            'payable_type' => BotUser::class,
            'payable_id' => $botUser->id,
        ]);
    }

    function callUniqueAmount(string $price, Invoice $invoice): string
    {
        $method = new ReflectionMethod(CardPaymentQuery::class, 'uniqueAmount');
        $method->setAccessible(true);

        return $method->invoke(new CardPaymentQuery, $price, $invoice);
    }

    beforeEach(function () {
        CardPaymentUniqueAmountTestState::$queue = [];
    });

    it('adds the random offset to the price when nothing collides', function () {
        $bot = $this->makeBot();
        $invoice = makeInvoiceForUniqueAmountTest($bot, '100000');

        CardPaymentUniqueAmountTestState::$queue = [42];

        expect(callUniqueAmount('100000', $invoice))->toBe('100042');
    });

    it('retries past an amount already taken by another pending attempt on the same bot', function () {
        $bot = $this->makeBot();
        $collidingInvoice = makeInvoiceForUniqueAmountTest($bot, '100000');
        $attempt = ToCardAttempt::create(['card_number' => '6037-9900-0000-0000', 'amount' => '100042']);
        billing()->attemptPayment($collidingInvoice, $attempt);

        $invoice = makeInvoiceForUniqueAmountTest($bot, '100000');
        CardPaymentUniqueAmountTestState::$queue = [42, 7];

        expect(callUniqueAmount('100000', $invoice))->toBe('100007');
    });

    it('does not offer an amount already taken by a pending attempt on a different bot', function () {
        $botA = $this->makeBot();
        $botB = $this->makeBot();

        $collidingInvoice = makeInvoiceForUniqueAmountTest($botB, '100000');
        $attempt = ToCardAttempt::create(['card_number' => '6037-9900-0000-0000', 'amount' => '100042']);
        billing()->attemptPayment($collidingInvoice, $attempt);

        $invoice = makeInvoiceForUniqueAmountTest($botA, '100000');
        CardPaymentUniqueAmountTestState::$queue = [42];

        expect(callUniqueAmount('100000', $invoice))->toBe('100042');
    });

    it('ignores a matching amount once that attempt is no longer pending', function () {
        $bot = $this->makeBot();
        $collidingInvoice = makeInvoiceForUniqueAmountTest($bot, '100000');
        $attempt = ToCardAttempt::create([
            'card_number' => '6037-9900-0000-0000',
            'amount' => '100042',
            'status' => 'succeed',
        ]);
        billing()->attemptPayment($collidingInvoice, $attempt);

        $invoice = makeInvoiceForUniqueAmountTest($bot, '100000');
        CardPaymentUniqueAmountTestState::$queue = [42];

        expect(callUniqueAmount('100000', $invoice))->toBe('100042');
    });
}
