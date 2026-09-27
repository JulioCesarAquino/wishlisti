<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_rsvp_premium')->default(false)->after('is_premium');
            $table->boolean('rsvp_collect_companions')->default(false)->after('is_rsvp_premium');
            $table->json('rsvp_required_fields')->nullable()->after('rsvp_collect_companions');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['is_rsvp_premium', 'rsvp_collect_companions', 'rsvp_required_fields']);
        });
    }
};
