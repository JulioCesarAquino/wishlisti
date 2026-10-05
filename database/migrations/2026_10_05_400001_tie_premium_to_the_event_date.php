<?php

use App\Models\Events\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Premium is bought for an event on a date, and its event features end
 * some days after it — so one purchase can't be reused, edit after edit,
 * for party after party. The purchase keeps the date it was bought for.
 *
 * Purchases already made get the same rule: they end on their event's date
 * (or, without one, the day they were paid) plus the default grace period.
 */
return new class extends Migration
{
    private const GRACE_DAYS = 60;

    public function up(): void
    {
        Schema::table('premium_purchases', function (Blueprint $table) {
            $table->date('event_date')->nullable()->after('amount');
        });

        DB::table('premium_purchases')
            ->join('events', 'events.id', '=', 'premium_purchases.event_id')
            ->where('premium_purchases.status', 'paid')
            ->select('premium_purchases.id', 'premium_purchases.paid_at', 'events.event_date')
            ->orderBy('premium_purchases.id')
            ->each(function (object $purchase): void {
                $date = $purchase->event_date ?? ($purchase->paid_at ? Carbon::parse($purchase->paid_at)->toDateString() : null);

                if (! $date) {
                    return;
                }

                DB::table('premium_purchases')->where('id', $purchase->id)->update(['event_date' => $date]);

                DB::table('feature_grants')
                    ->where('premium_purchase_id', $purchase->id)
                    ->where('grantable_type', (new Event)->getMorphClass())
                    ->update([
                        'expires_at' => Carbon::parse($date, Event::TIMEZONE)->addDays(self::GRACE_DAYS)->endOfDay()->utc(),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('feature_grants')->whereNotNull('premium_purchase_id')->update(['expires_at' => null]);

        Schema::table('premium_purchases', function (Blueprint $table) {
            $table->dropColumn('event_date');
        });
    }
};
