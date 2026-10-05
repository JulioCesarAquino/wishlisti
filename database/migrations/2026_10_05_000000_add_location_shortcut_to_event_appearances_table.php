<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the home tab shows the "Como chegar" button(s) under the date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_appearances', function (Blueprint $table) {
            $table->boolean('show_location_shortcut')->default(true)->after('cover_effect_intensity');
        });
    }

    public function down(): void
    {
        Schema::table('event_appearances', function (Blueprint $table) {
            $table->dropColumn('show_location_shortcut');
        });
    }
};
