<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Companions get fields of their own: the guest gives a WhatsApp, the
 * children they bring just a name and an age. Until the host changes them,
 * they stay what they were — the guest's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_rsvp_settings', function (Blueprint $table) {
            $table->json('companion_fields')->nullable()->after('fields');
        });

        DB::table('event_rsvp_settings')->whereNotNull('fields')->update(['companion_fields' => DB::raw('fields')]);
    }

    public function down(): void
    {
        Schema::table('event_rsvp_settings', function (Blueprint $table) {
            $table->dropColumn('companion_fields');
        });
    }
};
