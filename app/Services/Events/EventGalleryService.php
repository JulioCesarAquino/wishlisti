<?php

namespace App\Services\Events;

use App\Models\Events\Event;
use App\Support\Picture;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The gallery's photos, in light copies: the grid squares only need a
 * small picture, and the photo opened in full needs no more than a screen;
 * the originals, straight from a phone, have several MB each.
 *
 * Each photo gets, once:
 * - thumb: its shorter side 600 px (the squares are ~300 px wide, twice
 *   that for sharp screens);
 * - large: its longer side 1600 px, for the photo opened in full;
 * - placeholder: 24 px, inlined in the page and shown blurred while the
 *   thumb is on its way.
 */
class EventGalleryService
{
    public const THUMB = 600;

    public const LARGE = 1600;

    public const PLACEHOLDER = 24;

    /**
     * Until its copies are ready, a photo is served as it is (and the
     * copies are made after the page is sent).
     *
     * @return list<array{thumb: string, full: string, placeholder: string|null}>
     */
    public function photos(Event $event): array
    {
        $disk = Storage::disk('public');
        $photos = [];
        $missing = false;

        foreach ($event->gallery ?? [] as $path) {
            if (! $this->isPrepared($path)) {
                $missing = true;
                $photos[] = ['thumb' => $disk->url($path), 'full' => $disk->url($path), 'placeholder' => null];

                continue;
            }

            $photos[] = [
                'thumb' => $disk->url($this->copy($path, 'thumb')),
                'full' => $disk->url($this->copy($path, 'large')),
                'placeholder' => 'data:image/jpeg;base64,'.base64_encode((string) $disk->get($this->copy($path, 'placeholder'))),
            ];
        }

        if ($missing) {
            $this->prepareLater($event);
        }

        return $photos;
    }

    /**
     * Makes the copies after the response is sent: converting takes a few
     * seconds, no one should wait for it. One at a time per event, so two
     * visits don't convert the same photos twice.
     */
    public function prepareLater(Event $event): void
    {
        defer(fn () => Cache::lock("event-gallery:{$event->id}", 600)
            ->get(fn () => $this->prepare($event)));
    }

    /**
     * Makes the copies the event's photos don't have yet. A photo that
     * can't be converted (not a picture, too large) is skipped: it keeps
     * being served as it is.
     */
    public function prepare(Event $event): int
    {
        $prepared = 0;

        foreach ($event->gallery ?? [] as $path) {
            if ($this->isPrepared($path)) {
                continue;
            }

            // Each photo takes a second or two on a small server.
            set_time_limit(60);

            try {
                $this->render($path);
                $prepared++;
            } catch (Throwable $exception) {
                Log::warning('Could not make the light copies of a gallery photo.', [
                    'event_id' => $event->id,
                    'source' => $path,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $prepared;
    }

    private function render(string $path): void
    {
        $disk = Storage::disk('public');
        $picture = Picture::decode((string) $disk->get($path));
        $shorter = min($picture->width(), $picture->height());
        $scale = min(1, self::THUMB / $shorter);

        $disk->put($this->copy($path, 'thumb'), Picture::jpeg($picture->cover(
            max(1, (int) round($picture->width() * $scale)),
            max(1, (int) round($picture->height() * $scale)),
        ), 78));
        $disk->put($this->copy($path, 'large'), Picture::jpeg($picture->fit(self::LARGE), 82));
        // Written last: it's what tells the photo is ready.
        $disk->put($this->copy($path, 'placeholder'), Picture::jpeg($picture->fit(self::PLACEHOLDER), 60));
    }

    private function isPrepared(string $path): bool
    {
        return Storage::disk('public')->exists($this->copy($path, 'placeholder'));
    }

    /**
     * Named after the original: a new photo gets new copies.
     */
    private function copy(string $path, string $size): string
    {
        return 'events/gallery-previews/'.sha1($path)."/{$size}.jpg";
    }
}
