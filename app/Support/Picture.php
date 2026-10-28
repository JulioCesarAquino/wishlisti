<?php

namespace App\Support;

use GdImage;
use RuntimeException;

/**
 * A picture decoded with GD, to make light copies of it: phone photos come
 * with several MB and sideways, with only an EXIF tag telling which side
 * is up (browsers follow it, GD doesn't).
 */
final class Picture
{
    /**
     * Decoding takes about 5 bytes per pixel: a 12 MP phone photo needs
     * ~60 MB, more than PHP's default limit allows next to the request.
     * The limit is raised up to this while the picture is open; pictures
     * that need more aren't decoded.
     */
    private const MEMORY_CEILING = 512 * 1024 * 1024;

    /**
     * Room left for the copy, on top of the picture itself: the largest
     * copy made is 1600 px on its longer side.
     */
    private const COPY_PIXELS = 1600 * 1600;

    private function __construct(
        private readonly GdImage $image,
        private readonly int $orientation,
    ) {}

    /**
     * @throws RuntimeException when it isn't a picture, or it's too large to open
     */
    public static function decode(string $contents): self
    {
        self::makeRoomFor($contents);

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw new RuntimeException('Not an image.');
        }

        return new self($image, self::orientationOf($contents));
    }

    /**
     * Width as it's shown, standing the right way up.
     */
    public function width(): int
    {
        return $this->turned() ? imagesy($this->image) : imagesx($this->image);
    }

    public function height(): int
    {
        return $this->turned() ? imagesx($this->image) : imagesy($this->image);
    }

    /**
     * A width×height copy, the right way up: scaled to cover it, with the
     * overflow cropped off evenly on both sides.
     */
    public function cover(int $width, int $height): GdImage
    {
        // Scaled before it's turned: turning the small copy is cheap.
        [$targetWidth, $targetHeight] = $this->turned() ? [$height, $width] : [$width, $height];
        $sourceWidth = imagesx($this->image);
        $sourceHeight = imagesy($this->image);
        $scale = max($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $cropWidth = (int) round($targetWidth / $scale);
        $cropHeight = (int) round($targetHeight / $scale);

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        // Transparent PNGs get a white background instead of a black one.
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled(
            $canvas, $this->image,
            0, 0,
            intdiv($sourceWidth - $cropWidth, 2), intdiv($sourceHeight - $cropHeight, 2),
            $targetWidth, $targetHeight,
            $cropWidth, $cropHeight,
        );

        return $this->upright($canvas);
    }

    /**
     * A copy that fits in a size×size box, keeping the proportions (never
     * larger than the picture itself).
     */
    public function fit(int $size): GdImage
    {
        $scale = min(1, $size / max($this->width(), $this->height()));

        return $this->cover(
            max(1, (int) round($this->width() * $scale)),
            max(1, (int) round($this->height() * $scale)),
        );
    }

    public static function jpeg(GdImage $image, int $quality = 80): string
    {
        ob_start();
        imageinterlace($image, true);
        imagejpeg($image, null, $quality);

        return (string) ob_get_clean();
    }

    /**
     * Orientations 5 to 8 have the picture lying on its side.
     */
    private function turned(): bool
    {
        return $this->orientation >= 5;
    }

    private function upright(GdImage $image): GdImage
    {
        // imagerotate() turns counterclockwise.
        $image = match ($this->orientation) {
            3 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if (in_array($this->orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($this->orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        return $image;
    }

    /**
     * The EXIF orientation (1 to 8): only JPEGs carry it.
     */
    private static function orientationOf(string $contents): int
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($contents, "\xFF\xD8")) {
            return 1;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contents));
        $orientation = (int) ($exif['Orientation'] ?? 1);

        return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    /**
     * Running out of memory is a fatal error, which can't be caught: check
     * first, and give up (throw) when even the ceiling isn't enough.
     */
    private static function makeRoomFor(string $contents): void
    {
        $size = @getimagesizefromstring($contents);

        if ($size === false) {
            throw new RuntimeException('Not an image.');
        }

        $needed = memory_get_usage() + ($size[0] * $size[1] + self::COPY_PIXELS) * 5 + 16 * 1024 * 1024;
        $limit = self::bytes((string) ini_get('memory_limit'));

        if ($limit < 0 || $needed <= $limit) {
            return;
        }

        if ($needed > self::MEMORY_CEILING || ini_set('memory_limit', (string) $needed) === false) {
            throw new RuntimeException("Picture too large ({$size[0]}×{$size[1]}).");
        }
    }

    private static function bytes(string $value): int
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
