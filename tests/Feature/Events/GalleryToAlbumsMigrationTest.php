<?php

namespace Tests\Feature\Events;

use App\Models\Events\Event;
use App\Models\Events\EventAlbum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Each event's gallery became its first album, with every photo, in order.
 */
class GalleryToAlbumsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_gallery_photo_moves_to_a_default_album(): void
    {
        $withPhotos = Event::factory()->create();
        $trashed = Event::factory()->create();
        $trashed->delete();
        $withoutPhotos = Event::factory()->create();

        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2026_11_03_000000_create_event_albums_table.php'])->assertSuccessful();

        DB::table('events')->where('id', $withPhotos->id)->update(['gallery' => json_encode(['events/gallery/b.jpg', 'events/gallery/a.jpg', 'events/gallery/c.jpg'])]);
        DB::table('events')->where('id', $trashed->id)->update(['gallery' => json_encode(['events/gallery/t.jpg'])]);
        DB::table('events')->where('id', $withoutPhotos->id)->update(['gallery' => json_encode([])]);

        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_03_000000_create_event_albums_table.php'])->assertSuccessful();

        $album = EventAlbum::where('event_id', $withPhotos->id)->sole();
        $this->assertSame(EventAlbum::DEFAULT_NAME, $album->name);
        $this->assertSame(['events/gallery/b.jpg', 'events/gallery/a.jpg', 'events/gallery/c.jpg'], $album->photos);

        // Events in the trash keep their photos too, for when they're restored.
        $this->assertSame(['events/gallery/t.jpg'], EventAlbum::where('event_id', $trashed->id)->sole()->photos);
        $this->assertFalse(EventAlbum::where('event_id', $withoutPhotos->id)->exists());
    }
}
