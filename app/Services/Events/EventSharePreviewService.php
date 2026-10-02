<?php

namespace App\Services\Events;

use App\Models\Events\Event;
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

    /**
     * Decoding takes about 5 bytes per pixel: a 12 MP phone photo needs
     * ~60 MB, more than PHP's default limit allows next to the request.
     * The limit is raised up to this while the copy is made; pictures that
     * need more are served as they are.
     */
    private const MEMORY_CEILING = 512 * 1024 * 1024;

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
        $this->makeRoomFor($contents);

        $image = imagecreatefromstring($contents);

        if ($image === false) {
            throw new \RuntimeException('Not an image.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = max(self::WIDTH / $width, self::HEIGHT / $height);
        $cropWidth = (int) round(self::WIDTH / $scale);
        $cropHeight = (int) round(self::HEIGHT / $scale);

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        // Transparent PNGs get a white background instead of a black one.
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled(
            $canvas, $image,
            0, 0,
            intdiv($width - $cropWidth, 2), intdiv($height - $cropHeight, 2),
            self::WIDTH, self::HEIGHT,
            $cropWidth, $cropHeight,
        );

        unset($image);

        ob_start();
        imagejpeg($canvas, null, 80);

        return (string) ob_get_clean();
    }

    /**
     * Running out of memory is a fatal error, which can't be caught: check
     * first, and give up (throw) when even the ceiling isn't enough.
     */
    private function makeRoomFor(string $contents): void
    {
        $size = getimagesizefromstring($contents);

        if ($size === false) {
            throw new \RuntimeException('Not an image.');
        }

        $needed = memory_get_usage() + ($size[0] * $size[1] + self::WIDTH * self::HEIGHT) * 5 + 16 * 1024 * 1024;
        $limit = $this->bytes((string) ini_get('memory_limit'));

        if ($limit < 0 || $needed <= $limit) {
            return;
        }

        if ($needed > self::MEMORY_CEILING || ini_set('memory_limit', (string) $needed) === false) {
            throw new \RuntimeException("Picture too large ({$size[0]}×{$size[1]}).");
        }
    }

    private function bytes(string $value): int
    {
        $number = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
