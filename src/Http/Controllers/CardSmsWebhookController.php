<?php

namespace TelegramBotEssentials\GatewayCard\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use TelegramBotEssentials\GatewayCard\Services\CardSmsMatcher;

class CardSmsWebhookController extends Controller
{
    public function sms(Request $request): JsonResponse
    {
        $bot = tenancy()->tenant;

        // Needed before any settings()->get() call - it resolves the
        // current bot's id internally.
        wHook()->setBot($bot);

        if (! settings()->get('billing.gateways.card.status')) {
            return tbeApiResponse()->error('Card gateway is disabled', 404);
        }

        $secret = settings()->get('billing.gateways.card.sms_secret');

        if (! $secret) {
            return tbeApiResponse()->error('SMS auto-verification is not configured', 404);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        $signature = (string) $request->header('X-Signature');

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return tbeApiResponse()->error('Invalid signature', 401);
        }

        $text = (string) $request->input('text');

        if ($text === '') {
            return tbeApiResponse()->error('Missing SMS text', 422);
        }

        // Always 200 from here down, match or not - "no matching pending
        // attempt" is not a transient failure and must not trigger the
        // forwarder app's retry-with-backoff.
        app(CardSmsMatcher::class)->handle($bot, $text);

        return tbeApiResponse()->success();
    }
}
