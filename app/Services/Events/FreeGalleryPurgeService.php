<?php

namespace App\Services\Events;

use App\Models\Events\Event;
use App\Models\Events\EventAlbum;
use App\Notifications\Events\FreeGalleryExpiringNotification;
use App\Support\PlatformSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Free events keep their photos for a while after the event; Premium
 * events, for good (Event::photosDeleteOn()). A few days before, the hosts
 * are told, with the way to keep them; on the day, the photos go.
 */
class FreeGalleryPurgeService
{
    public function __construct(private EventGalleryService $gallery) {}

    /**
     * Warns the hosts if the photos go within the warning days and they
     * weren't told yet. Returns whether it did.
     */
    public function warnIfDue(Event $event): bool
    {
        $deleteOn = $event->photosDeleteOn();

        if (! $deleteOn || $event->photos_purge_warned_at || $event->photoCount() === 0) {
            return false;
        }

        $today = Carbon::now(Event::TIMEZONE)->startOfDay();

        if ($today->lt($deleteOn->copy()->subDays((int) config('premium.free_photo_warning_days')))) {
            return false;
        }

        foreach ($event->hosts() as $host) {
            try {
                $host->notify(new FreeGalleryExpiringNotification($event, $deleteOn));
            } catch (Throwable $exception) {
                Log::warning('Could not tell a host their free gallery is expiring.', [
                    'event_id' => $event->id,
                    'user_id' => $host->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        // Also when an e-mail failed: no second one every day.
        $event->forceFill(['photos_purge_warned_at' => now()])->saveQuietly();

        return true;
    }

    /**
     * Deletes the photos once their day has come. Returns how many went.
     */
    public function purgeIfDue(Event $event): int
    {
        $deleteOn = $event->photosDeleteOn();

        if (! $deleteOn || Carbon::now(Event::TIMEZONE)->startOfDay()->lt($deleteOn)) {
            return 0;
        }

        $deleted = 0;

        $event->albums->each(function (EventAlbum $album) use (&$deleted): void {
            foreach ($album->photoPaths() as $path) {
                $this->gallery->forget($path);
                $deleted++;
            }

            $album->forceFill(['photos' => null, 'cover_photo' => null])->saveQuietly();
        });

        if ($deleted > 0) {
            $event->forceFill(['photos_purged_at' => now()])->saveQuietly();

            Log::info('Deleted the photos of a free event past its retention.', [
                'event_id' => $event->id,
                'photos' => $deleted,
                'retention_days' => PlatformSettings::freePhotoRetentionDays(),
            ]);
        }

        return $deleted;
    }
}
