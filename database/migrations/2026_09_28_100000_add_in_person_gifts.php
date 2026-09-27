<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gifts delivered in person: the guest reserves items without paying
 * online, and hands the gift over themselves. They're orders too, so they
 * reuse items, stock, anonymity and the financial-record protections.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment')->default('online')->after('status');
        });

        Schema::create('event_gift_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('allow_in_person')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_gift_settings');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('fulfillment');
        });
    }
};
