<?php

namespace TelegramBotEssentials\GatewayCard\Models;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Storage;
use TelegramBotEssentials\Billing\Models\Abstract\PaymentAttempt;
use TelegramBotEssentials\Essence\Support\WebhookContext;
use TelegramBotEssentials\Essence\Traits\HasMessageMeta;
use TelegramBotEssentials\GatewayCard\Services\UniqueAmountResolver;

class ToCardAttempt extends PaymentAttempt
{
    use HasMessageMeta;

    /** The member backed out of paying, or the invoice was settled another way. */
    public const STATUS_CANCELLED = 'cancelled';

    /** The member started a fresh card attempt for the same invoice. */
    public const STATUS_SUPERSEDED = 'superseded';

    protected $guarded = [
        'id',
        'updated_at',
        'deleted_at',
        'created_at',
    ];

    /**
     * Ends an attempt that will never be paid or reviewed, so it stops
     * counting as a pending payment. Attempts already resolved (succeed /
     * failed / closed) are left alone.
     */
    public function close(string $status): void
    {
        if ($this->status !== null) {
            return;
        }

        $this->status = $status;
        $this->save();
    }

    protected function attemptSucceedHook(): void
    {
        $this->creditExtraAmountToWallet();
    }

    protected function attemptFailedHook(): void
    {
        $this->setAttribute('rejected_at', now());
        $this->save();
    }

    public function getInfoPhotoAttribute(): ?string
    {
        if (! $this->info_photo_path) {
            return null;
        }

        $disk = Storage::disk(config('tbe-gateway-card.disk', 'local'));

        return $disk->exists($this->info_photo_path) ? $disk->get($this->info_photo_path) : null;
    }

    /**
     * The unique-amount offset (amount minus the invoice's real price) only
     * becomes the member's money once the payment is confirmed - crediting
     * earlier would hand it out without any transfer. Wallet::addAmount()
     * acts on the ambient webhook user, but the manual admin-accept path
     * runs as the admin, so switch to the paying member for the credit and
     * restore afterwards. A failure here (e.g. Telegram hiccup while
     * addAmount() notifies the member) must not stop the payment itself
     * from being marked succeeded.
     */
    private function creditExtraAmountToWallet(): void
    {
        $extra = BigDecimal::of($this->amount)->minus($this->invoice->price);

        if (! $extra->isPositive() || ! app(UniqueAmountResolver::class)->canCreditWallet()) {
            return;
        }

        $originalContext = WebhookContext::capture();

        try {
            (new WebhookContext(
                botId: $this->invoice->bot_id,
                botUserId: $this->invoice->bot_user_id,
                bot: $this->invoice->bot,
                botUser: $this->invoice->botUser,
            ))->apply();

            wallet()->addAmount((string) $extra);
        } catch (\Throwable $e) {
            tbeLog('gateway-card')->error('Failed to credit unique-amount extra to wallet', [
                'attempt_id' => $this->getKey(),
                'extra_amount' => (string) $extra,
                'exception' => $e,
            ]);
        } finally {
            $originalContext?->apply();
        }
    }
}
