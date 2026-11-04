<?php

namespace Tests\Feature\Events;

use App\Enums\Premium\Feature;
use App\Filament\Pages\PlatformSettingsPage;
use App\Filament\Resources\Events\Events\Pages\ManageEventAlbums;
use App\Models\Events\Event;
use App\Models\Events\EventAlbum;
use App\Models\Premium\FeatureGrant;
use App\Models\User;
use App\Support\PlatformSettings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Photo albums are premium: free events get a single gallery, up to the
 * admin's free photo limit.
 */
class EventAlbumsTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutDefer();
        config(['premium.free_photo_limit' => 3]);

        $this->host = User::factory()->create(['is_admin' => false]);
        $this->event = Event::factory()->create(['user_id' => $this->host->id, 'is_published' => true]);
    }

    /**
     * @return array<int, string>
     */
    private function photos(int $count, string $prefix = 'p'): array
    {
        return array_map(function (int $index) use ($prefix): string {
            $path = "events/gallery/{$prefix}{$index}.jpg";
            Storage::disk('public')->put($path, UploadedFile::fake()->image('photo.jpg', 40, 30)->getContent());

            return $path;
        }, range(1, $count));
    }

    /**
     * @param  array<int, string>  $photos
     */
    private function album(array $photos, string $name = 'Galeria', int $position = 0): EventAlbum
    {
        return EventAlbum::factory()->create([
            'event_id' => $this->event->id,
            'name' => $name,
            'position' => $position,
            'photos' => $photos,
        ]);
    }

    private function page(): Testable
    {
        $this->actingAs($this->host);

        return Livewire::test(ManageEventAlbums::class, ['record' => $this->event->getRouteKey()]);
    }

    // Free plan limit

    public function test_the_free_limit_comes_from_the_admin_settings_with_10_by_default(): void
    {
        config(['premium.free_photo_limit' => 10]);
        $this->assertSame(10, $this->event->photoLimit());

        PlatformSettings::set(['free_photo_limit' => 25]);
        $this->assertSame(25, $this->event->fresh()->photoLimit());
    }

    public function test_the_admin_changes_the_free_limit_on_the_settings_page(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(PlatformSettingsPage::class)
            ->assertSet('data.free_photo_limit', 3)
            ->set('data.free_photo_limit', 12)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(12, PlatformSettings::freePhotoLimit());
    }

    public function test_a_free_event_cannot_save_photos_past_the_limit(): void
    {
        $album = $this->album($this->photos(3));

        try {
            $album->update(['photos' => [...$album->photos, ...$this->photos(1, 'extra')]]);
            $this->fail('The fourth photo should have been refused.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('até 3 fotos', $exception->errors()['photos'][0]);
        }

        $this->assertCount(3, $album->fresh()->photos);
    }

    public function test_the_free_limit_counts_every_album_of_the_event(): void
    {
        $this->event->grantFeature(Feature::Albums);
        $this->album($this->photos(2, 'a'), 'Cerimônia');
        $party = $this->album([], 'Festa', 1);
        $this->event->syncFeatures([]);

        $this->assertFalse($this->event->fresh()->canAddPhotos(2));

        $this->expectException(ValidationException::class);
        $party->fresh()->update(['photos' => $this->photos(2, 'b')]);
    }

    public function test_photos_past_the_limit_from_before_are_kept_but_none_added(): void
    {
        // Sent before the limit existed (or while the event was premium).
        $album = EventAlbum::withoutEvents(fn () => $this->album($this->photos(5)));

        // Still all on the page.
        $this->get("/{$this->event->slug}")->assertInertia(fn ($page) => $page->has('event.albums.0.photos', 5));

        // Can be renamed, reordered and trimmed…
        $album->update(['name' => 'Nossas fotos', 'photos' => array_reverse($album->photos)]);
        $album->update(['photos' => array_slice($album->photos, 0, 4)]);
        $this->assertCount(4, $album->fresh()->photos);

        // …but not grown.
        $this->expectException(ValidationException::class);
        $album->update(['photos' => [...$album->photos, ...$this->photos(1, 'new')]]);
    }

    public function test_the_panel_refuses_uploads_past_the_limit_with_a_clear_message(): void
    {
        $this->page()
            ->callAction(TestAction::make('create')->table(), [
                'name' => 'Galeria',
                'photos' => [
                    UploadedFile::fake()->image('1.jpg'),
                    UploadedFile::fake()->image('2.jpg'),
                    UploadedFile::fake()->image('3.jpg'),
                    UploadedFile::fake()->image('4.jpg'),
                ],
            ])
            ->assertHasActionErrors(['photos' => 'max']);

        $this->assertSame(0, $this->event->albums()->count());

        $this->page()
            ->assertSee('Plano gratuito: 0 de 3 fotos')
            ->callAction(TestAction::make('create')->table(), [
                'name' => 'Galeria',
                'photos' => [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.jpg')],
            ])
            ->assertHasNoActionErrors();

        $album = $this->event->albums()->sole();
        $this->assertCount(2, $album->photos);

        $this->page()
            ->assertSee('Plano gratuito: 2 de 3 fotos')
            ->callAction(TestAction::make('edit')->table($album), [
                'photos' => [...$album->photos, UploadedFile::fake()->image('3.jpg'), UploadedFile::fake()->image('4.jpg')],
            ])
            ->assertHasActionErrors(['photos' => 'max']);

        $this->assertCount(2, $album->fresh()->photos);
    }

    // Albums are premium

    public function test_a_free_event_has_a_single_album(): void
    {
        $this->album($this->photos(1));

        $this->assertFalse($this->event->canCreateAlbum());

        $this->expectException(ValidationException::class);
        $this->album([], 'Festa', 1);
    }

    public function test_the_panel_blocks_a_second_album_on_a_free_event(): void
    {
        $this->album($this->photos(1));

        $this->page()
            ->assertActionVisible('discoverPremium')
            ->assertActionDisabled(TestAction::make('create')->table());

        $this->assertSame(1, $this->event->albums()->count());
    }

    public function test_a_premium_event_has_as_many_albums_and_photos_as_it_wants(): void
    {
        $this->event->grantFeature(Feature::Albums);

        $this->page()
            ->assertActionHidden('discoverPremium')
            ->callAction(TestAction::make('create')->table(), ['name' => 'Cerimônia', 'photos' => [UploadedFile::fake()->image('1.jpg')]])
            ->assertHasNoActionErrors()
            ->callAction(TestAction::make('create')->table(), [
                'name' => 'Festa',
                'photos' => array_map(fn (int $i) => UploadedFile::fake()->image("{$i}.jpg"), range(1, 5)),
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(['Cerimônia', 'Festa'], $this->event->albums()->pluck('name')->all());
        $this->assertSame(6, $this->event->photoCount());
    }

    public function test_when_the_premium_ends_the_albums_stay_on_the_page_but_no_new_ones(): void
    {
        $this->event->featureGrants()->create([
            'feature' => Feature::Albums,
            'source' => FeatureGrant::SOURCE_PURCHASE,
            'expires_at' => now()->addDay(),
        ]);
        $this->album($this->photos(2, 'a'), 'Cerimônia');
        $this->album($this->photos(4, 'b'), 'Festa', 1);

        $this->travel(2)->days();
        $this->event->unsetRelation('featureGrants');

        $this->get("/{$this->event->slug}")->assertInertia(fn ($page) => $page
            ->has('event.albums', 2)
            ->where('event.albums.1.name', 'Festa')
            ->has('event.albums.1.photos', 4));

        $this->assertFalse($this->event->canCreateAlbum());
        $this->assertFalse($this->event->canAddPhotos());

        $this->page()
            ->assertSee('Os álbuns que você já criou continuam na página')
            ->assertActionDisabled(TestAction::make('create')->table());
    }

    // Public page

    public function test_the_page_sends_the_albums_in_order_with_the_cover_first_on_the_fan(): void
    {
        $this->event->grantFeature(Feature::Albums);
        $party = $this->album($this->photos(5, 'party'), 'Festa', 1);
        $this->album($this->photos(1, 'ceremony'), 'Cerimônia');
        // An album without photos isn't shown.
        $this->album([], 'Vazio', 2);
        $party->update(['cover_photo' => 'events/gallery/party3.jpg']);

        $this->get("/{$this->event->slug}")->assertInertia(fn ($page) => $page
            ->has('event.albums', 2)
            ->where('event.albums.0.name', 'Cerimônia')
            ->where('event.albums.1.name', 'Festa')
            ->where('event.albums.1.cover.full', fn (string $url) => str_contains($url, sha1('events/gallery/party3.jpg')))
            // Three photos behind the cover, the cover not among them.
            ->has('event.albums.1.fan', 3)
            ->where('event.albums.1.fan.0.full', fn (string $url) => str_contains($url, sha1('events/gallery/party1.jpg')))
            // The album itself keeps the host's order.
            ->has('event.albums.1.photos', 5)
            ->where('event.albums.1.photos.2.full', fn (string $url) => str_contains($url, sha1('events/gallery/party3.jpg'))));
    }

    public function test_a_cover_that_is_no_longer_in_the_album_falls_back_to_the_first_photo(): void
    {
        $album = $this->album($this->photos(2));
        $album->update(['cover_photo' => 'events/gallery/p2.jpg']);
        $album->update(['photos' => ['events/gallery/p1.jpg']]);

        $this->assertNull($album->fresh()->cover_photo);
        $this->assertSame('events/gallery/p1.jpg', $album->fresh()->coverPath());
    }

    public function test_a_photo_downloads_as_an_attachment(): void
    {
        $this->event->update(['title' => 'Ana e Pedro']);
        $album = $this->album($this->photos(2), 'Cerimônia');

        $this->get(route('events.photos.download', [$this->event->slug, $album->id, EventAlbum::photoKey('events/gallery/p2.jpg')]))
            ->assertOk()
            ->assertDownload('ana-e-pedro-cerimonia-2.jpg');
    }

    public function test_the_download_link_is_tied_to_the_event_and_the_album(): void
    {
        $album = $this->album($this->photos(1));
        $other = EventAlbum::factory()->create(['photos' => ['events/gallery/p1.jpg']]);
        $key = EventAlbum::photoKey('events/gallery/p1.jpg');

        $this->get(route('events.photos.download', [$this->event->slug, $other->id, $key]))->assertNotFound();
        $this->get(route('events.photos.download', [$this->event->slug, $album->id, str_repeat('0', 16)]))->assertNotFound();

        // An unpublished event's photos only for who runs it.
        $this->event->update(['is_published' => false]);
        $this->get(route('events.photos.download', [$this->event->slug, $album->id, $key]))->assertNotFound();
    }

    public function test_the_albums_feature_comes_with_the_premium_plan(): void
    {
        $this->assertContains(Feature::Albums, config('premium.event_features'));
        $this->assertSame(Event::class, Feature::Albums->appliesTo());
    }
}
