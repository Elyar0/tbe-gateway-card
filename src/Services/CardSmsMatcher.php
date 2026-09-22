<?php

namespace TelegramBotEssentials\GatewayCard\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use TelegramBotEssentials\Essence\Models\Bot;
use TelegramBotEssentials\Essence\Support\WebhookContext;
use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;
use TelegramBotEssentials\GatewayCard\Services\SmsParsers\BankSmsParserFactory;

class CardSmsMatcher
{
    /**
     * Matching window: how long after the member submits proof a bank SMS
     * is still eligible to auto-confirm that specific attempt.
     */
    private const MATCH_WINDOW_MINUTES = 30;

    public function handle(Bot $bot, string $rawText): void
    {
        // settings()->get() and wHook()->api() both need the bot in place
        // first. telegramApi() (not `new Api()`) is required here - it's
        // the only constructor path that wires in LaravelHttpClient, which
        // is what makes outbound calls fakeable/testable and routed through
        // this app's HTTP client instead of the SDK's default Guzzle client.
        // Nothing here needs a specific user yet - that only applies once a
        // single attempt (and its member) is identified, in autoAccept().
        wHook()->setBot($bot);
        wHook()->setApi(telegramApi($bot->bot_token));

        $parser = BankSmsParserFactory::make(settings()->get('billing.gateways.card.sms_bank'));

        if ($parser === null) {
            tbeLog('gateway-card')->debug('SMS webhook: no bank parser configured for this bot, ignoring');

            return;
        }

        $rialAmount = $parser->parseDepositAmount($rawText);

        if ($rialAmount === null) {
            // Normal traffic - OTPs, withdrawals, other bank messages from
            // the same forwarded number. Not an error.
            tbeLog('gateway-card')->debug('SMS webhook: message is not a recognized deposit notification, ignoring');

            return;
        }

        $amount = $this->convertToTenantCurrency($rialAmount);

        if ($amount === null) {
            tbeLog('gateway-card')->warning('SMS webhook: cannot match, billing currency is not Rial-based', [
                'currency' => settings()->get('billing.currency'),
            ]);

            return;
        }

        // to_card_attempts has no bot_id column and isn't BelongsToTenant -
        // tenancy here is row-scoping on a single shared database, so an
        // unscoped query would happily match another tenant's pending
        // attempt at the same amount. Scope through the (tenant-scoped)
        // invoice relation instead.
        $candidates = ToCardAttempt::whereNull('status')
            ->whereNotNull('received_at')
            ->where('received_at', '>=', now()->subMinutes(self::MATCH_WINDOW_MINUTES))
            ->where('amount', $amount)
            ->whereHas('invoice', fn ($query) => $query->where('bot_id', $bot->id))
            ->get();

        if ($candidates->count() === 1) {
            $this->autoAccept($candidates->first(), $amount);

            return;
        }

        $this->reportUnmatched($amount, $candidates->count());
    }

    private function autoAccept(ToCardAttempt $toCardAttempt, string $amount): void
    {
        $invoice = $toCardAttempt->invoice;

        // attemptSucceed() -> markAsPaid() fires InvoicePaid, whose listener
        // renders the member's keyboard and messages them - that (and
        // runForUser()'s own user-restore step) needs a fully-populated,
        // user-bound webhook context, not just an api() client. This is the
        // same apply() a queued job uses to resume a captured context.
        (new WebhookContext(
            botId: $invoice->bot_id,
            botUserId: $invoice->bot_user_id,
            bot: $invoice->bot,
            botUser: $invoice->botUser,
        ))->apply();

        $toCardAttempt->received_amount = $amount;
        $toCardAttempt->save();

        $toCardAttempt->attemptSucceed();

        $toCardAttempt->messageMeta->lockAction(
            __('tbe-gateway-card::invoice.to_card.lock-keys.auto_verified_by_sms'),
            customEmoji: '✅'
        );

        tbeLog('gateway-card')->info('Card payment auto-verified via SMS', [
            'attempt_id' => $toCardAttempt->getKey(),
            'amount' => $amount,
        ]);
    }

    private function reportUnmatched(string $amount, int $candidateCount): void
    {
        tbeLog('gateway-card')->warning('SMS amount did not uniquely match a pending card attempt', [
            'amount' => $amount,
            'candidate_count' => $candidateCount,
        ]);

        $chatId = settings()->get('billing.gateways.card.transactions_chat_id');

        if (! $chatId) {
            return;
        }

        wHook()->api()->sendMessage([
            'chat_id' => $chatId,
            'text' => __('tbe-gateway-card::invoice.to_card.text.admin-sms_unmatched', [
                'amount' => currency()->priceFormat($amount),
                'candidateCount' => $candidateCount,
            ]),
        ]);
    }

    /**
     * Blu Bank (and every bank we're likely to add) reports Rial. The
     * tenant's own billing currency is what ToCardAttempt::amount is stored
     * in, so convert before comparing - IRT (Toman) is Rial / 10, IRR is a
     * straight passthrough. Anything else means to-card isn't a coherent
     * payment method for this tenant's currency, so refuse to guess an FX
     * rate rather than risk a wrong match.
     */
    private function convertToTenantCurrency(string $rialAmount): ?string
    {
        $currency = settings()->get('billing.currency');

        return match ($currency) {
            'IRR' => $rialAmount,
            'IRT' => (string) BigDecimal::of($rialAmount)->dividedBy(10, 0, RoundingMode::DOWN),
            default => null,
        };
    }
}
