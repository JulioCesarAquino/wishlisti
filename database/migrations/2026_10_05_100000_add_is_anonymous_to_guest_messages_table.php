<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymous guestbook messages: the name becomes optional and, when given,
 * only the admin sees it — like anonymous gifts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_messages', function (Blueprint $table) {
            $table->string('author_name')->nullable()->change();
            $table->boolean('is_anonymous')->default(false)->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('guest_messages', function (Blueprint $table) {
            $table->dropColumn('is_anonymous');
        });

        DB::table('guest_messages')->whereNull('author_name')->update(['author_name' => 'Anônimo']);

        Schema::table('guest_messages', function (Blueprint $table) {
            $table->string('author_name')->nullable(false)->change();
        });
    }
};
