<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the hosts got the e-mail about a message (see
 * GuestMessagesNotifyHostsCommand): each one goes in a single e-mail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_messages', function (Blueprint $table) {
            $table->timestamp('hosts_notified_at')->nullable()->after('approved_at');
        });

        // The messages sent before this were seen on the panel already: no
        // e-mail about them.
        DB::table('guest_messages')->update(['hosts_notified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('guest_messages', function (Blueprint $table) {
            $table->dropColumn('hosts_notified_at');
        });
    }
};
