<?php

use App\Models\Guests\Guest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How many companions each guest may bring — the event's default, and one of
 * the guest's own for a whole family — and the guest's personal invitation
 * link, which the host can make the only way to confirm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_rsvp_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_companions')->nullable()->after('companion_fields');
            $table->boolean('invite_only')->default(false)->after('max_companions');
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->string('invite_code', 16)->nullable()->unique()->after('identifier');
            $table->unsignedTinyInteger('max_companions')->nullable()->after('rsvp_children_count');
        });

        DB::table('guests')->whereNull('invite_code')->orderBy('id')->select('id')->chunkById(500, function ($guests): void {
            foreach ($guests as $guest) {
                DB::table('guests')->where('id', $guest->id)->update(['invite_code' => Guest::newInviteCode()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropUnique(['invite_code']);
            $table->dropColumn(['invite_code', 'max_companions']);
        });

        Schema::table('event_rsvp_settings', function (Blueprint $table) {
            $table->dropColumn(['max_companions', 'invite_only']);
        });
    }
};
