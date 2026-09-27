<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('premium_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('status');
            $table->string('payment_id')->nullable()->index();
            $table->string('payment_method')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // Ties each purchased grant to the purchase that paid for it, so a
        // refund revokes exactly those.
        Schema::table('feature_grants', function (Blueprint $table) {
            $table->foreignId('premium_purchase_id')->nullable()->after('source')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('feature_grants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('premium_purchase_id');
        });

        Schema::dropIfExists('premium_purchases');
    }
};
