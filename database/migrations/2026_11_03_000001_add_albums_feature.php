<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Photo albums (and photos past the free limit) become the premium feature
 * "albums". Events that already have the plan — those with the full gift
 * list — get it too, from the same source, purchase and end, so nobody is
 * charged again for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('feature_grants')
            ->where('feature', 'full_gift_list')
            ->get()
            ->each(function (object $grant): void {
                $exists = DB::table('feature_grants')
                    ->where('grantable_type', $grant->grantable_type)
                    ->where('grantable_id', $grant->grantable_id)
                    ->where('feature', 'albums')
                    ->exists();

                if (! $exists) {
                    DB::table('feature_grants')->insert([
                        'grantable_type' => $grant->grantable_type,
                        'grantable_id' => $grant->grantable_id,
                        'feature' => 'albums',
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
        DB::table('feature_grants')->where('feature', 'albums')->delete();
    }
};
