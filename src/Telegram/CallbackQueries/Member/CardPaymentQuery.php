<?php

namespace TelegramBotEssentials\GatewayCard\Telegram\CallbackQueries\Member;

use Brick\Math\BigDecimal;
use Illuminate\Contracts\Container\BindingResolutionException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Billing\Telegram\Features\Member\InvoiceFeature;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Exceptions\FeatureIsDisabled;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Telegram\CallbackQueries\CallbackQuery;
use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;
use TelegramBotEssentials\GatewayCard\Services\UniqueAmountResolver;
use TelegramBotEssentials\GatewayCard\Telegram\Features\Member\CardPaymentFeature;

class CardPaymentQuery extends CallbackQuery
{
    protected string $type = 'CARD_PAYMENT';

    protected int $perm = Roles::MEMBER->value;

    /**
     * @throws BindingResolutionException
     * @throws FeatureIsDisabled
     * @throws LogicException
     * @throws TelegramSDKException
     */
    public function toCard(Invoice $invoice): void
    {
        dependsOn(settings()->get('billing.gateways.card.status'));
        dependsOn(settings()->get('billing.gateways.card.card_number'));
        dependsOn(settings()->get('billing.gateways.card.card_name'));
        dependsOn(settings()->get('billing.gateways.card.transactions_chat_id'));

        // Close the invoice's previous attempt first: otherwise its own
        // amount would count as a competing payment against the new one.
        if ($invoice->paymentAttempt instanceof ToCardAttempt) {
            $invoice->paymentAttempt->close(ToCardAttempt::STATUS_SUPERSEDED);
        }

        $resolver = app(UniqueAmountResolver::class);
        $price = $invoice->price;
        $offset = $resolver->resolve($price);
        $amount = $offset === null ? $price : (string) BigDecimal::of($price)->plus($offset);

        $toCardAttempt = ToCardAttempt::create([
            'card_number' => settings()->get('billing.gateways.card.card_number'),
            'amount' => $amount,
        ]);

        billing()->attemptPayment($invoice, $toCardAttempt);

        tbeLog('gateway-card')->info('Card payment initiated', [
            'invoice_id' => $invoice->getKey(),
            'attempt_id' => $toCardAttempt->getKey(),
            'amount' => $toCardAttempt->amount,
        ]);

        $text = __('tbe-gateway-card::invoice.to_card.text.user-pay_message', [
            'amount' => $this->copyableAmount($amount),
            'cardNumber' => settings()->get('billing.gateways.card.card_number'),
            'cardName' => settings()->get('billing.gateways.card.card_name'),
        ]);

        if ($offset !== null) {
            $text .= "\r\n\r\n".__('tbe-gateway-card::invoice.to_card.text.unique_amount_notice');

            if ($resolver->canCreditWallet()) {
                $text .= ' '.__('tbe-gateway-card::invoice.to_card.text.unique_amount_wallet_notice', [
                    'extraAmount' => currency()->priceFormat($offset),
                ]);
            }
        }

        if ($offerSummary = InvoiceFeature::offerSummary($invoice)) {
            $text .= "\r\n\r\n".$offerSummary;
        }

        wHook()->user()->changeState(
            encodeAnswerState(
                $this->type,
                'pay_to_card',
                [
                    'invoice' => $invoice->id,
                ]
            )
        );

        $invoice->messageMeta->lockAction(__('tbe-gateway-card::invoice.to_card.lock-keys.user-waiting_for_payment'));

        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser->peer_id,
            'text' => $text,
            'reply_markup' => wHook()->user()->getKeyboard(),
            'parse_mode' => 'HTML',
        ]);
        $this->answer(__('tbe-gateway-card::invoice.to_card.answers.attempting'));
    }

    /**
     * Only the number sits inside <code> so tapping it copies just that,
     * without the currency symbol.
     */
    private function copyableAmount(string $amount): string
    {
        return '<code>'.currency()->currencyFormat($amount, thousandSeparator: ',').'</code> '.currency()->getCurrentCurrencySymbol();
    }

    public function isEnabled(): bool
    {
        return CardPaymentFeature::isCardPaymentEnabled();
    }
}
