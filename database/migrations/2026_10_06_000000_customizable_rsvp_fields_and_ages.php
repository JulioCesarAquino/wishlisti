<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The RSVP form, field by field: each one not asked, optional or required —
 * the age among them. And the event's "children under X don't pay", with
 * each guest's age, or how many children came with a guest who only gave a
 * headcount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_rsvp_settings', function (Blueprint $table) {
            $table->json('fields')->nullable()->after('collect_companions');
            $table->unsignedTinyInteger('child_age_limit')->nullable()->after('fields');
        });

        // What was required stays required; WhatsApp and e-mail were always
        // on the form, the CPF only when required.
        DB::table('event_rsvp_settings')->orderBy('id')->each(function (object $settings): void {
            $required = json_decode((string) $settings->required_fields, true) ?: ['whatsapp'];

            DB::table('event_rsvp_settings')->where('id', $settings->id)->update(['fields' => json_encode([
                'whatsapp' => in_array('whatsapp', $required, true) ? 'required' : 'optional',
                'email' => in_array('email', $required, true) ? 'required' : 'optional',
                'cpf' => in_array('cpf', $required, true) ? 'required' : 'hidden',
                'age' => 'hidden',
            ])]);
        });

        Schema::table('event_rsvp_settings', function (Blueprint $table) {
            $table->dropColumn('required_fields');
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->unsignedTinyInteger('age')->nullable()->after('cpf');
            $table->unsignedTinyInteger('rsvp_children_count')->nullable()->after('rsvp_guests_count');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['age', 'rsvp_children_count']);
        });

        Schema::table('event_rsvp_settings', function (Blueprint $table) {
            $table->json('required_fields')->nullable()->after('collect_companions');
        });

        DB::table('event_rsvp_settings')->orderBy('id')->each(function (object $settings): void {
            $fields = json_decode((string) $settings->fields, true) ?: [];

            DB::table('event_rsvp_settings')->where('id', $settings->id)->update([
                'required_fields' => json_encode(array_values(array_intersect(['whatsapp', 'email', 'cpf'], array_keys(array_filter($fields, fn ($mode) => $mode === 'required'))))),
            ]);
        });

        Schema::table('event_rsvp_settings', function (Blueprint $table) {
            $table->dropColumn(['fields', 'child_age_limit']);
        });
    }
};
