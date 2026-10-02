<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where to pay a purchase left open (the Pix code or boleto page), so the
 * host goes back to it instead of starting another payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premium_purchases', function (Blueprint $table) {
            $table->string('payment_url', 2048)->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('premium_purchases', function (Blueprint $table) {
            $table->dropColumn('payment_url');
        });
    }
};
