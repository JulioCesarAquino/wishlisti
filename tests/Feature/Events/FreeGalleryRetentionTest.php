<?php

namespace Tests\Feature\Events;

use App\Enums\Premium\Feature;
use App\Models\Events\Event;
use App\Models\Events\EventAlbum;
use App\Models\Premium\PremiumPurchase;
use App\Notifications\Events\FreeGalleryExpiringNotification;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Free events keep their photos for a while after the event (90 days by
 * default); events that had the Premium keep them for good.
 */
class FreeGalleryRetentionTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Notification::fake();
        $this->withoutDefer();

        $this->travelTo(now('America/Sao_Paulo')->setDate(2026, 6, 1)->setTime(12, 0));
        PlatformSettings::set(['photo_retention_started_on' => '2026-01-01']);

        $this->event = Event::factory()->create(['event_date' => '2026-03-01', 'is_published' => true]);
        $path = 'events/gallery/a.jpg';
        Storage::disk('public')->put($path, UploadedFile::fake()->image('a.jpg', 1200, 800)->getContent());
        EventAlbum::factory()->create(['event_id' => $this->event->id, 'photos' => [$path]]);
    }

    private function purge(): void
    {
        $this->artisan('gallery:purge-free')->assertSuccessful();
    }

    public function test_the_photos_go_some_days_after_the_event(): void
    {
        $this->assertSame('2026-05-30', $this->event->photosDeleteOn()?->toDateString());

        $this->purge();

        Storage::disk('public')->assertMissing('events/gallery/a.jpg');
        Storage::disk('public')->assertMissing('events/gallery-previews/'.sha1('events/gallery/a.jpg').'/thumb.jpg');
        $this->assertNull($this->event->albums()->first()->photos);
        $this->assertNotNull($this->event->fresh()->photos_purged_at);
    }

    public function test_the_days_are_set_on_the_panel(): void
    {
        PlatformSettings::set(['free_photo_retention_days' => 120]);

        $this->purge();

        Storage::disk('public')->assertExists('events/gallery/a.jpg');
        $this->assertSame('2026-06-29', $this->event->photosDeleteOn()?->toDateString());
    }

    public function test_the_hosts_are_warned_once_some_days_before(): void
    {
        PlatformSettings::set(['free_photo_retention_days' => 95]);
        // Deleted on 2026-06-04: three days from now.

        $this->purge();
        $this->purge();

        Notification::assertSentToTimes($this->event->user, FreeGalleryExpiringNotification::class, 1);
        Storage::disk('public')->assertExists('events/gallery/a.jpg');
    }

    public function test_no_warning_before_the_warning_days(): void
    {
        PlatformSettings::set(['free_photo_retention_days' => 120]);

        $this->purge();

        Notification::assertNothingSent();
    }

    public function test_events_that_had_the_premium_keep_their_photos(): void
    {
        $this->event->grantFeature(Feature::Albums);
        // Expired since: still a Premium event.
        $this->event->featureGrants()->update(['expires_at' => now()->subMonth()]);

        $this->purge();

        Storage::disk('public')->assertExists('events/gallery/a.jpg');
        $this->assertNull($this->event->fresh()->photosDeleteOn());
    }

    public function test_a_paid_premium_purchase_keeps_them_too(): void
    {
        PremiumPurchase::query()->forceCreate([
            'event_id' => $this->event->id,
            'user_id' => $this->event->user_id,
            'amount' => 39.90,
            'event_date' => '2026-03-01',
            'status' => PremiumPurchase::STATUS_PAID,
            'paid_at' => now()->subMonths(4),
        ]);

        $this->purge();

        Storage::disk('public')->assertExists('events/gallery/a.jpg');
    }

    public function test_events_without_a_date_keep_their_photos(): void
    {
        $this->event->update(['event_date' => null]);

        $this->purge();

        Storage::disk('public')->assertExists('events/gallery/a.jpg');
    }

    public function test_events_already_past_when_the_rule_began_count_from_then(): void
    {
        PlatformSettings::set(['photo_retention_started_on' => '2026-05-20']);

        $this->assertSame('2026-08-18', $this->event->photosDeleteOn()?->toDateString());

        $this->purge();

        Storage::disk('public')->assertExists('events/gallery/a.jpg');
    }

    public function test_moving_the_date_brings_a_new_warning(): void
    {
        $this->event->forceFill(['photos_purge_warned_at' => now()])->save();

        $this->event->update(['event_date' => '2026-05-01']);

        $this->assertNull($this->event->fresh()->photos_purge_warned_at);
    }

    public function test_the_warning_e_mail_says_when_and_how_to_keep_them(): void
    {
        $mail = (new FreeGalleryExpiringNotification($this->event, $this->event->photosDeleteOn()))
            ->toMail($this->event->user);

        $this->assertStringContainsString('30 de maio', $mail->subject);
        $this->assertStringContainsString('Premium', (string) $mail->render());
    }
}
