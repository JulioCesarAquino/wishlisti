<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Custom gifts, quotas (more than one unit) and an unlimited list become
 * the premium feature "full_gift_list". Events that are already premium —
 * those taking payments — get it too, from the same source (and the same
 * purchase, so a refund still revokes it), instead of being offered the
 * plan again.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('feature_grants')
            ->where('feature', 'payments')
            ->get()
            ->each(function (object $grant): void {
                $exists = DB::table('feature_grants')
                    ->where('grantable_type', $grant->grantable_type)
                    ->where('grantable_id', $grant->grantable_id)
                    ->where('feature', 'full_gift_list')
                    ->exists();

                if (! $exists) {
                    DB::table('feature_grants')->insert([
                        'grantable_type' => $grant->grantable_type,
                        'grantable_id' => $grant->grantable_id,
                        'feature' => 'full_gift_list',
                        'source' => $grant->source,
                        'premium_purchase_id' => $grant->premium_purchase_id,
                        'expires_at' => $grant->expires_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('feature_grants')->where('feature', 'full_gift_list')->delete();
    }
};
