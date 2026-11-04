<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The event page a would-be host came from, through the "Feita com
 * Wishlisti" line at the bottom of every public page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invite_requests', function (Blueprint $table) {
            $table->foreignId('referred_by_event_id')->nullable()->after('whatsapp')->constrained('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invite_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_event_id');
        });
    }
};
