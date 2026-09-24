<?php

namespace TelegramBotEssentials\GatewayCard\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use TelegramBotEssentials\GatewayCard\Services\CardSmsMatcher;

class UnmatchedCardSms extends Model
{
    use Prunable;

    protected $table = 'unmatched_card_sms';

    // Not BelongsToTenant: tenancy()->initialize() only runs behind the real
    // HTTP middleware chain, not wHook()->setBot() alone (used by tests and
    // by CardSmsMatcher itself) - same reasoning as ToCardAttempt, see
    // CardSmsMatcher::handle()'s comment on the candidates query.
    protected $guarded = [
        'id',
        'updated_at',
        'created_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function prunable(): Builder
    {
        return static::where('received_at', '<', now()->subMinutes(CardSmsMatcher::MATCH_WINDOW_MINUTES));
    }
}
