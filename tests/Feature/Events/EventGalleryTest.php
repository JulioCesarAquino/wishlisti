<?php

namespace Tests\Feature\Events;

use App\Models\Events\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutDefer();
    }

    private function storeImage(string $path, int $width, int $height): string
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image('photo.jpg', $width, $height)->getContent());

        return $path;
    }

    private function copy(string $path, string $size): string
    {
        return 'events/gallery-previews/'.sha1($path)."/{$size}.jpg";
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function sizeOf(string $path): array
    {
        return array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2);
    }

    public function test_saving_the_gallery_makes_light_copies_of_the_photos(): void
    {
        $event = Event::factory()->create(['is_published' => true]);
        $event->update(['gallery' => [$this->storeImage('events/gallery/a.jpg', 3000, 2000)]]);

        $this->assertSame([900, 600], $this->sizeOf($this->copy('events/gallery/a.jpg', 'thumb')));
        $this->assertSame([1600, 1067], $this->sizeOf($this->copy('events/gallery/a.jpg', 'large')));
        $this->assertSame([24, 16], $this->sizeOf($this->copy('events/gallery/a.jpg', 'placeholder')));
    }

    public function test_the_page_serves_the_copies(): void
    {
        $event = Event::factory()->create([
            'is_published' => true,
            'gallery' => [$this->storeImage('events/gallery/a.jpg', 1200, 1200)],
        ]);

        $this->get("/{$event->slug}")
            ->assertInertia(fn ($page) => $page
                ->where('event.gallery.0.thumb', Storage::disk('public')->url($this->copy('events/gallery/a.jpg', 'thumb')))
                ->where('event.gallery.0.full', Storage::disk('public')->url($this->copy('events/gallery/a.jpg', 'large')))
                ->where('event.gallery.0.placeholder', fn (string $placeholder) => str_starts_with($placeholder, 'data:image/jpeg;base64,')));
    }

    public function test_photos_without_copies_are_served_as_they_are_until_the_copies_are_made(): void
    {
        $event = Event::factory()->create(['is_published' => true]);
        // Saved without the model events: as the photos sent before the copies existed.
        Event::withoutEvents(fn () => $event->update(['gallery' => [$this->storeImage('events/gallery/a.jpg', 800, 600)]]));

        $this->get("/{$event->slug}")
            ->assertInertia(fn ($page) => $page
                ->where('event.gallery.0.thumb', Storage::disk('public')->url('events/gallery/a.jpg'))
                ->where('event.gallery.0.placeholder', null));

        Storage::disk('public')->assertExists($this->copy('events/gallery/a.jpg', 'thumb'));
    }

    public function test_a_sideways_phone_photo_is_turned_the_right_way_up(): void
    {
        // 1600×1200 as stored, with EXIF orientation 6: shown 1200×1600.
        $jpeg = UploadedFile::fake()->image('photo.jpg', 1600, 1200)->getContent();
        $tiff = "MM\x00\x2A\x00\x00\x00\x08"."\x00\x01"."\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00"."\x00\x00\x00\x00";
        $app1 = "\xFF\xE1".pack('n', strlen($tiff) + 8)."Exif\x00\x00".$tiff;
        Storage::disk('public')->put('events/gallery/turned.jpg', substr($jpeg, 0, 2).$app1.substr($jpeg, 2));

        Event::factory()->create(['gallery' => ['events/gallery/turned.jpg']]);

        $this->assertSame([600, 800], $this->sizeOf($this->copy('events/gallery/turned.jpg', 'thumb')));
        $this->assertSame([1200, 1600], $this->sizeOf($this->copy('events/gallery/turned.jpg', 'large')));
    }

    public function test_a_broken_photo_is_served_as_it_is(): void
    {
        Storage::disk('public')->put('events/gallery/broken.jpg', 'not an image');

        $event = Event::factory()->create(['is_published' => true, 'gallery' => ['events/gallery/broken.jpg']]);

        $this->get("/{$event->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('event.gallery.0.full', Storage::disk('public')->url('events/gallery/broken.jpg')));
    }

    public function test_the_command_converts_the_photos_sent_before_the_copies_existed(): void
    {
        $event = Event::factory()->create();
        Event::withoutEvents(fn () => $event->update([
            'gallery' => [$this->storeImage('events/gallery/a.jpg', 800, 600), $this->storeImage('events/gallery/b.jpg', 600, 800)],
        ]));

        $this->artisan('gallery:previews')
            ->expectsOutput('Fotos convertidas: 2.')
            ->assertSuccessful();

        Storage::disk('public')->assertExists($this->copy('events/gallery/b.jpg', 'placeholder'));
    }
}
