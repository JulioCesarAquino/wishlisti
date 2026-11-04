<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the location and RSVP tabs were turned off for the event being over:
 * done once, so a host who turns them back on keeps them on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('tabs_closed_after_event_at')->nullable()->after('event_time');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('tabs_closed_after_event_at');
        });
    }
};
