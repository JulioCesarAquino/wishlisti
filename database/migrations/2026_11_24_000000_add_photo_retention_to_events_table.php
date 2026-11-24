<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Free events keep their photos for a while after the event (90 days by
 * default); Premium ones, for good. When the hosts were warned, and when
 * the photos went. The rule starts now: events already past count from
 * today, so nobody loses photos without notice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('photos_purge_warned_at')->nullable()->after('tabs_closed_after_event_at');
            $table->timestamp('photos_purged_at')->nullable()->after('photos_purge_warned_at');
        });

        DB::table('platform_settings')->updateOrInsert(
            ['key' => 'photo_retention_started_on'],
            ['value' => now('America/Sao_Paulo')->toDateString(), 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['photos_purge_warned_at', 'photos_purged_at']);
        });

        DB::table('platform_settings')->where('key', 'photo_retention_started_on')->delete();
    }
};
