<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the event starts (next to its date, and what the countdown counts
 * down to), and when each location's part does — ceremony at 16h, party at
 * 18h30. Both optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->time('event_time')->nullable()->after('event_date');
        });

        Schema::table('event_locations', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('event_locations', function (Blueprint $table) {
            $table->dropColumn('start_time');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('event_time');
        });
    }
};
