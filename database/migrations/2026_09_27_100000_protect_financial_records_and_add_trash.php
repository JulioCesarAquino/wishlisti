<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a guest (or an event) used to cascade into its orders, erasing
 * the financial history for good. Orders now block those deletes at the
 * database level, and guests, gifts and events get a trash (soft deletes)
 * so an accidental delete can be undone. Events with paid orders are
 * archived instead of deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['guest_id']);
            $table->dropForeign(['event_id']);

            $table->foreign('guest_id')->references('id')->on('guests')->restrictOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->restrictOnDelete();
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('event_products', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('is_published');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('archived_at');
        });

        Schema::table('event_products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['guest_id']);
            $table->dropForeign(['event_id']);

            $table->foreign('guest_id')->references('id')->on('guests')->cascadeOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
    }
};
