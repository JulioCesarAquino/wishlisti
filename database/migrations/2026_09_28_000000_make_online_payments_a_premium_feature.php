<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Receiving gifts online becomes the premium feature "payments", which
 * absorbs "gift_givers" (seeing who gave each gift — pointless to hide once
 * only premium events take payments). Events already set up to take
 * payments keep taking them: they get the feature, as if the admin had
 * unlocked it. Guests may now also give anonymously.
 */
return new class extends Migration
{
    private const EVENT_MORPH = 'App\Models\Events\Event';

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_anonymous')->default(false)->after('message');
        });

        DB::table('feature_grants')->where('feature', 'gift_givers')->update(['feature' => 'payments']);

        $alreadyGranted = DB::table('feature_grants')
            ->where('grantable_type', self::EVENT_MORPH)
            ->where('feature', 'payments')
            ->pluck('grantable_id');

        DB::table('event_payment_settings')
            ->whereNotNull('mp_access_token')
            ->where('mp_access_token', '!=', '')
            ->whereNotIn('event_id', $alreadyGranted)
            ->pluck('event_id')
            ->each(fn (int $eventId) => DB::table('feature_grants')->insert([
                'grantable_type' => self::EVENT_MORPH,
                'grantable_id' => $eventId,
                'feature' => 'payments',
                'source' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    public function down(): void
    {
        // Grants given only because the event had credentials can't be told
        // apart from the admin's, so they all go back to "gift_givers".
        DB::table('feature_grants')->where('feature', 'payments')->update(['feature' => 'gift_givers']);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_anonymous');
        });
    }
};
