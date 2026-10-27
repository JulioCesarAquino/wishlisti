<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many people fit in the event — guests and companions — and whether
 * the children under the event's age limit take a place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_rsvp_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('capacity')->nullable()->after('invite_only');
            $table->boolean('capacity_excludes_children')->default(false)->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('event_rsvp_settings', function (Blueprint $table) {
            $table->dropColumn(['capacity', 'capacity_excludes_children']);
        });
    }
};
