<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use TelegramBotEssentials\GatewayCard\Models\ToCardAttempt;

return new class extends Migration
{
    /**
     * Attempts left open before they were closed explicitly: ones whose
     * invoice was already settled some other way, and ones no live invoice
     * points at anymore (replaced by a newer attempt, or the invoice was
     * deleted). None of them can be paid or reviewed any more.
     */
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        $type = (new ToCardAttempt)->getMorphClass();

        DB::table('to_card_attempts')
            ->whereNull('status')
            ->whereIn('id', fn ($query) => $query->select('payment_attempt_id')
                ->from('invoices')
                ->where('payment_attempt_type', $type)
                ->whereNull('deleted_at')
                ->whereIn('status', ['paid', 'failed']))
            ->update(['status' => ToCardAttempt::STATUS_CANCELLED]);

        DB::table('to_card_attempts')
            ->whereNull('status')
            ->whereNotIn('id', fn ($query) => $query->select('payment_attempt_id')
                ->from('invoices')
                ->where('payment_attempt_type', $type)
                ->whereNull('deleted_at')
                ->whereNotNull('payment_attempt_id'))
            ->update(['status' => ToCardAttempt::STATUS_SUPERSEDED]);
    }

    public function down(): void {}
};
