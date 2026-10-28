<?php

namespace App\Services\Events;

use App\Models\Events\Event;
use App\Support\Picture;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The picture on the card WhatsApp (and Facebook, Telegram…) shows for the
 * event's link: the share image the host picked, or else the cover.
 *
 * Apps skip pictures that are too heavy, and covers straight from a phone
 * often are, so a light 1200×630 JPEG copy (the size those cards use) is
 * made once per picture and served instead.
 */
class EventSharePreviewService
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    public function url(Event $event): ?string
    {
        $source = $event->share_image ?: $event->cover_image;

        if (! $source) {
            return null;
        }

        $disk = Storage::disk('public');
        // Named after the source: a new picture gets a new copy (and a new
        // URL, so apps that cached the old card fetch it again).
        $preview = 'events/share-previews/'.sha1($source).'.jpg';

        if (! $disk->exists($preview)) {
            try {
                $disk->put($preview, $this->render((string) $disk->get($source)));
            } catch (Throwable $exception) {
                Log::warning('Could not make the share preview of an event.', [
                    'event_id' => $event->id,
                    'source' => $source,
                    'exception' => $exception->getMessage(),
                ]);

                return $disk->exists($source) ? $disk->url($source) : null;
            }
        }

        return $disk->url($preview);
    }

    /**
     * Scales the picture to cover 1200×630 and crops the overflow, centered.
     */
    private function render(string $contents): string
    {
        return Picture::jpeg(Picture::decode($contents)->cover(self::WIDTH, self::HEIGHT));
    }
}
