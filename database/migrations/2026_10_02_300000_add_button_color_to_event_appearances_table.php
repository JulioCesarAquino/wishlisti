<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The color of the page's main buttons (gifts, payment, "Como chegar"…),
 * apart from the secondary color, which stays for decorative details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_appearances', function (Blueprint $table) {
            $table->string('button_color')->nullable()->after('secondary_color');
        });
    }

    public function down(): void
    {
        Schema::table('event_appearances', function (Blueprint $table) {
            $table->dropColumn('button_color');
        });
    }
};
