<?php

namespace Tests\Feature\Platform;

use App\Models\Events\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_the_demos_in_the_catalog_order(): void
    {
        Storage::fake('public');
        User::factory()->create(['is_admin' => true]);
        $this->artisan('demos:sync', ['--images' => storage_path('framework/testing/no-demo-images')])->assertSuccessful();
        // A real event never shows up as an example.
        Event::factory()->create(['is_published' => true]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('welcome')
                ->where('demos.0.slug', 'demo-casamento')
                ->where('demos.1.slug', 'demo-cha-de-bebe')
                ->where('demos.2.slug', 'demo-aniversario')
                ->has('demos', 3)
                ->where('pricing.premium_price', fn ($price) => (float) $price > 0));
    }

    public function test_it_works_without_demos(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->has('demos', 0));
    }

    public function test_the_meta_pixel_is_only_there_with_an_id(): void
    {
        $this->get('/')->assertDontSee('fbevents.js', false);

        config(['services.meta.pixel_id' => '123456']);

        $this->get('/')->assertSee('fbevents.js', false);
        $this->get('/cadastro')->assertSee('fbevents.js', false);
    }

    public function test_guests_event_pages_never_get_the_pixel(): void
    {
        config(['services.meta.pixel_id' => '123456']);
        $event = Event::factory()->create(['is_published' => true]);

        $this->get("/{$event->slug}")->assertOk()->assertDontSee('fbevents.js', false);
    }
}
