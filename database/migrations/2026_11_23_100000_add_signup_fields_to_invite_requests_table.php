<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hosts now sign up on their own: their sign-up is kept with the invite
 * requests (already approved), along with the ad campaign that brought
 * them — invite requests keep the campaign too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invite_requests', function (Blueprint $table) {
            $table->boolean('via_signup')->default(false)->after('status');
            $table->string('utm_source', 100)->nullable()->after('referred_by_event_id');
            $table->string('utm_medium', 100)->nullable()->after('utm_source');
            $table->string('utm_campaign', 150)->nullable()->after('utm_medium');
        });
    }

    public function down(): void
    {
        Schema::table('invite_requests', function (Blueprint $table) {
            $table->dropColumn(['via_signup', 'utm_source', 'utm_medium', 'utm_campaign']);
        });
    }
};
