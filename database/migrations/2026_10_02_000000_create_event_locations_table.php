<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An event can take place in more than one spot (ceremony and party, say),
 * each with a link of its own. The single address on the event becomes its
 * first location.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->text('address');
            $table->string('maps_url', 2048)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'slug']);
        });

        DB::table('events')->whereNotNull('address')->orderBy('id')->each(function (object $event): void {
            DB::table('event_locations')->insert([
                'event_id' => $event->id,
                'name' => 'Local do evento',
                'slug' => 'local',
                'address' => $event->address,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['address', 'latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('address')->nullable()->after('story');
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        // Only the first location fits back on the event.
        DB::table('event_locations')->orderBy('event_id')->orderBy('position')->orderBy('id')->get()
            ->unique('event_id')
            ->each(fn (object $location) => DB::table('events')->where('id', $location->event_id)->update([
                'address' => mb_substr($location->address, 0, 255),
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
            ]));

        Schema::dropIfExists('event_locations');
    }
};
