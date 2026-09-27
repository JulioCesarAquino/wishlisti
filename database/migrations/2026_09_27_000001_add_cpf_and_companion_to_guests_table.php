<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            // WhatsApp stops being mandatory: a premium event may ask only
            // for name + e-mail or name + CPF.
            $table->string('whatsapp')->nullable()->change();
            $table->string('cpf', 11)->nullable()->after('email');
            $table->foreignId('companion_of_guest_id')
                ->nullable()
                ->after('event_id')
                ->constrained('guests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('companion_of_guest_id');
            $table->dropColumn('cpf');
            $table->string('whatsapp')->nullable(false)->change();
        });
    }
};
