<?php

namespace Tests\Feature\Events;

use App\Models\Events\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventShareCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function storeImage(string $path, int $width, int $height): string
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image('photo.jpg', $width, $height)->getContent());

        return $path;
    }

    public function test_the_link_card_shows_the_event_title_and_description(): void
    {
        $event = Event::factory()->create([
            'is_published' => true,
            'title' => 'Kenia & Jefferson',
            'description' => "Aqui vamos contar a vocês,\n queridos amigos.",
        ]);

        $this->get("/{$event->slug}")
            ->assertOk()
            ->assertSee('<meta property="og:title" content="Kenia &amp; Jefferson">', false)
            ->assertSee('<meta property="og:description" content="Aqui vamos contar a vocês, queridos amigos.">', false)
            ->assertSee('<meta property="og:url" content="'.url("/{$event->slug}").'">', false)
            ->assertSee('<title>Kenia &amp; Jefferson</title>', false)
            ->assertDontSee('og:image', false);
    }

    public function test_without_a_description_the_card_tells_the_type_and_date(): void
    {
        $event = Event::factory()->create([
            'is_published' => true,
            'type' => 'casamento',
            'description' => null,
            'event_date' => '2026-11-15',
        ]);

        $this->get("/{$event->slug}")
            ->assertSee('<meta property="og:description" content="Casamento · 15 de novembro de 2026">', false);
    }

    public function test_the_cover_becomes_a_light_1200_by_630_picture(): void
    {
        $event = Event::factory()->create([
            'is_published' => true,
            'cover_image' => $this->storeImage('events/covers/cover.jpg', 1500, 2000),
        ]);

        $preview = 'events/share-previews/'.sha1('events/covers/cover.jpg').'.jpg';

        $this->get("/{$event->slug}")
            ->assertSee('<meta property="og:image" content="'.Storage::disk('public')->url($preview).'">', false);

        Storage::disk('public')->assertExists($preview);
        $this->assertSame([1200, 630], array_slice(getimagesizefromstring(Storage::disk('public')->get($preview)), 0, 2));
    }

    public function test_the_share_image_the_host_picked_wins_over_the_cover(): void
    {
        $event = Event::factory()->create([
            'is_published' => true,
            'cover_image' => $this->storeImage('events/covers/cover.jpg', 800, 600),
            'share_image' => $this->storeImage('events/share/logo.png', 1200, 630),
        ]);

        $this->get("/{$event->slug}")
            ->assertSee(Storage::disk('public')->url('events/share-previews/'.sha1('events/share/logo.png').'.jpg'), false);
    }

    public function test_a_picture_too_large_to_convert_is_served_as_it_is(): void
    {
        // 20000×20000: ~2 GB to decode. Only the header is read.
        $png = UploadedFile::fake()->image('tiny.png', 1, 1)->getContent();
        $huge = substr_replace($png, pack('NN', 20000, 20000), 16, 8);
        Storage::disk('public')->put('events/covers/huge.png', $huge);

        $event = Event::factory()->create([
            'is_published' => true,
            'cover_image' => 'events/covers/huge.png',
        ]);

        $this->get("/{$event->slug}")
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.Storage::disk('public')->url('events/covers/huge.png').'">', false);
    }

    public function test_a_broken_picture_falls_back_to_the_original(): void
    {
        Storage::disk('public')->put('events/covers/broken.jpg', 'not an image');

        $event = Event::factory()->create([
            'is_published' => true,
            'cover_image' => 'events/covers/broken.jpg',
        ]);

        $this->get("/{$event->slug}")
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.Storage::disk('public')->url('events/covers/broken.jpg').'">', false);
    }

    public function test_a_location_link_card_names_the_location(): void
    {
        $event = Event::factory()->withLocation(['name' => 'Festa', 'address' => 'Salão Azul, Asa Sul'])->create([
            'is_published' => true,
            'title' => 'Kenia e Jefferson',
        ]);

        $this->get("/{$event->slug}/localizacao/festa")
            ->assertSee('<meta property="og:title" content="Kenia e Jefferson · Festa">', false)
            ->assertSee('<meta property="og:description" content="Salão Azul, Asa Sul">', false);
    }
}
