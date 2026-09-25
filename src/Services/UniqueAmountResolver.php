<?php

namespace TelegramBotEssentials\GatewayCard\Services;

use Brick\Math\BigDecimal;
use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;
use TelegramBotEssentials\UserWallet\Services\Wallet;

class UniqueAmountResolver
{
    /**
     * Coarsest-first granularity ladder for the offset added on top of the
     * invoice price. A finer step is only tried once every multiple of the
     * current step (up to CEILING) is already claimed by another pending
     * attempt on this bot.
     */
    private const LADDER = [1000, 500, 300, 200, 100];

    private const CEILING = 5000;

    /**
     * Decides whether $price needs a unique offset added for this bot and,
     * if so, picks one that isn't already in use by another pending
     * ToCardAttempt. Returns null when no offset should be added.
     */
    public function resolve(string $price): ?string
    {
        $mode = $this->mode();

        if ($mode === 'disabled') {
            return null;
        }

        if ($mode === 'soft' && ! $this->isPending($price)) {
            return null;
        }

        return $this->pickOffset($price);
    }

    /**
     * user-wallet is an optional package, and its own admin toggle can turn
     * the wallet off even when installed - either way the offset is absorbed
     * instead of credited, and the member-facing message must not promise it.
     */
    public function canCreditWallet(): bool
    {
        return class_exists(Wallet::class) && (bool) settings()->get('billing.user_wallet.status');
    }

    /**
     * The setting used to be a plain CHECKBOX; existing bots may still have
     * that boolean stored. Treat anything that isn't already one of the
     * three modes as that legacy value.
     */
    private function mode(): string
    {
        $raw = settings()->get('billing.gateways.card.unique_amount');

        if (in_array($raw, ['hard', 'soft', 'disabled'], true)) {
            return $raw;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN) ? 'hard' : 'disabled';
    }

    private function pickOffset(string $price): string
    {
        foreach (self::LADDER as $step) {
            for ($offset = $step; $offset <= self::CEILING; $offset += $step) {
                if (! $this->isPending((string) BigDecimal::of($price)->plus($offset))) {
                    return (string) $offset;
                }
            }
        }

        // The whole ladder is exhausted - astronomically unlikely, but fall
        // back to the old one-shot scheme rather than blocking the payment.
        return (string) random_int(1, 99);
    }

    /**
     * to_card_attempts isn't tenant-scoped (see CardSmsMatcher), so scope
     * through the invoice relation to avoid matching another bot's attempt.
     */
    private function isPending(string $amount): bool
    {
        return ToCardAttempt::whereNull('status')
            ->where('amount', $amount)
            ->whereHas('invoice', fn ($query) => $query->where('bot_id', wHook()->bot()->id))
            ->exists();
    }
}
