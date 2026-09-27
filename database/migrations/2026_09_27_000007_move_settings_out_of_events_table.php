<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves appearance, RSVP form and payment settings into their own 1:1
 * tables, and the premium flags into feature grants, so the events table
 * only keeps what describes the event itself.
 */
return new class extends Migration
{
    private const EVENT_MORPH = 'App\Models\Events\Event';

    /** @var array<string, string> */
    private const FEATURE_COLUMNS = [
        'is_premium' => 'gift_givers',
        'is_rsvp_premium' => 'guest_list',
    ];

    public function up(): void
    {
        DB::table('events')->orderBy('id')->each(function (object $event): void {
            $timestamps = ['created_at' => now(), 'updated_at' => now()];

            DB::table('event_appearances')->insert([
                'event_id' => $event->id,
                'primary_color' => $event->primary_color,
                'secondary_color' => $event->secondary_color,
                'font_color_primary' => $event->font_color_primary,
                'font_color_secondary' => $event->font_color_secondary,
                'font_family' => $event->font_family,
                'cover_effect_intensity' => $event->cover_effect_intensity,
                ...$timestamps,
            ]);

            DB::table('event_rsvp_settings')->insert([
                'event_id' => $event->id,
                'collect_companions' => $event->rsvp_collect_companions,
                'required_fields' => $event->rsvp_required_fields,
                ...$timestamps,
            ]);

            // Copied as-is: both columns hold values already encrypted with
            // the app key.
            DB::table('event_payment_settings')->insert([
                'event_id' => $event->id,
                'mp_access_token' => $event->mp_access_token,
                'mp_public_key' => $event->mp_public_key,
                ...$timestamps,
            ]);

            foreach (self::FEATURE_COLUMNS as $column => $feature) {
                if ($event->{$column}) {
                    DB::table('feature_grants')->insert([
                        'grantable_type' => self::EVENT_MORPH,
                        'grantable_id' => $event->id,
                        'feature' => $feature,
                        'source' => 'admin',
                        ...$timestamps,
                    ]);
                }
            }
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'primary_color', 'secondary_color', 'font_color_primary', 'font_color_secondary',
                'font_family', 'cover_effect_intensity',
                'rsvp_collect_companions', 'rsvp_required_fields',
                'mp_access_token', 'mp_public_key',
                'is_premium', 'is_rsvp_premium',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('mp_access_token')->nullable();
            $table->text('mp_public_key')->nullable();
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_rsvp_premium')->default(false);
            $table->boolean('rsvp_collect_companions')->default(false);
            $table->json('rsvp_required_fields')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('font_color_primary')->nullable();
            $table->string('font_color_secondary')->nullable();
            $table->string('font_family')->nullable();
            $table->unsignedTinyInteger('cover_effect_intensity')->default(100);
        });

        DB::table('event_appearances')->orderBy('id')->each(fn (object $row) => DB::table('events')
            ->where('id', $row->event_id)
            ->update([
                'primary_color' => $row->primary_color,
                'secondary_color' => $row->secondary_color,
                'font_color_primary' => $row->font_color_primary,
                'font_color_secondary' => $row->font_color_secondary,
                'font_family' => $row->font_family,
                'cover_effect_intensity' => $row->cover_effect_intensity,
            ]));

        DB::table('event_rsvp_settings')->orderBy('id')->each(fn (object $row) => DB::table('events')
            ->where('id', $row->event_id)
            ->update([
                'rsvp_collect_companions' => $row->collect_companions,
                'rsvp_required_fields' => $row->required_fields,
            ]));

        DB::table('event_payment_settings')->orderBy('id')->each(fn (object $row) => DB::table('events')
            ->where('id', $row->event_id)
            ->update([
                'mp_access_token' => $row->mp_access_token,
                'mp_public_key' => $row->mp_public_key,
            ]));

        foreach (self::FEATURE_COLUMNS as $column => $feature) {
            $eventIds = DB::table('feature_grants')
                ->where('grantable_type', self::EVENT_MORPH)
                ->where('feature', $feature)
                ->pluck('grantable_id');

            DB::table('events')->whereIn('id', $eventIds)->update([$column => true]);
        }

        DB::table('feature_grants')->where('grantable_type', self::EVENT_MORPH)->delete();
        DB::table('event_appearances')->delete();
        DB::table('event_rsvp_settings')->delete();
        DB::table('event_payment_settings')->delete();
    }
};
