<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The children's age tells who's a child — whether or not they pay is a
 * separate choice. Events that already set an age did it for "don't pay".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_rsvp_settings', function (Blueprint $table) {
            $table->boolean('children_dont_pay')->default(false)->after('child_age_limit');
        });

        DB::table('event_rsvp_settings')->whereNotNull('child_age_limit')->update(['children_dont_pay' => true]);
    }

    public function down(): void
    {
        Schema::table('event_rsvp_settings', function (Blueprint $table) {
            $table->dropColumn('children_dont_pay');
        });
    }
};
