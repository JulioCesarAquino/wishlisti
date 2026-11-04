<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The gallery's photos go into albums. Each event's current gallery becomes
 * its first album, "Galeria", with the same files in the same order (the
 * light copies are named after the file, so they stay valid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('position')->default(0);
            // Paths on the public disk, in the host's order.
            $table->json('photos')->nullable();
            // One of the photos; without it, the first one is the cover.
            $table->string('cover_photo')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'position']);
        });

        DB::table('events')->whereNotNull('gallery')->orderBy('id')->each(function (object $event): void {
            $photos = array_values(array_filter((array) json_decode((string) $event->gallery, true), 'is_string'));

            if ($photos === []) {
                return;
            }

            DB::table('event_albums')->insert([
                'event_id' => $event->id,
                'name' => 'Galeria',
                'position' => 0,
                'photos' => json_encode($photos),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('gallery');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('gallery')->nullable()->after('share_message');
        });

        // Every album's photos back into one gallery, albums in their order.
        DB::table('event_albums')->orderBy('event_id')->orderBy('position')->orderBy('id')->get()
            ->groupBy('event_id')
            ->each(function ($albums, $eventId): void {
                $photos = $albums->flatMap(fn (object $album) => (array) json_decode((string) $album->photos, true))->values()->all();

                DB::table('events')->where('id', $eventId)->update(['gallery' => $photos === [] ? null : json_encode($photos)]);
            });

        Schema::dropIfExists('event_albums');
    }
};
