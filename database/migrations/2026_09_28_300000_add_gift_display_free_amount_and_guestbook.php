<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * For hosts who'd rather not ask for gifts: how the gift list shows on the
 * page (full list, discreet, or none at all), contributions of any amount
 * without picking an item, and a guestbook (premium) where guests leave a
 * message with or without a gift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_gift_settings', function (Blueprint $table) {
            $table->string('display_mode')->default('list')->after('event_id');
            $table->text('gift_message')->nullable()->after('display_mode');
            $table->boolean('allow_free_amount')->default(false)->after('allow_in_person');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_free_amount')->default(false)->after('fulfillment');
        });

        Schema::create('guest_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name');
            $table->text('message');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // Events already premium (taking payments) get the guestbook too,
        // from the same source and purchase.
        DB::table('feature_grants')->where('feature', 'payments')->get()->each(function (object $grant): void {
            $exists = DB::table('feature_grants')
                ->where('grantable_type', $grant->grantable_type)
                ->where('grantable_id', $grant->grantable_id)
                ->where('feature', 'guestbook')
                ->exists();

            if (! $exists) {
                DB::table('feature_grants')->insert([
                    'grantable_type' => $grant->grantable_type,
                    'grantable_id' => $grant->grantable_id,
                    'feature' => 'guestbook',
                    'source' => $grant->source,
                    'premium_purchase_id' => $grant->premium_purchase_id,
                    'expires_at' => $grant->expires_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('feature_grants')->where('feature', 'guestbook')->delete();

        Schema::dropIfExists('guest_messages');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_free_amount');
        });

        Schema::table('event_gift_settings', function (Blueprint $table) {
            $table->dropColumn(['display_mode', 'gift_message', 'allow_free_amount']);
        });
    }
};
