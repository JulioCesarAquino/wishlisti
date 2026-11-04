<?php

namespace App\Models\Events;

use App\Services\Events\EventGalleryService;
use Database\Factories\Events\EventAlbumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * A photo album of the event's gallery. Free events have a single one,
 * shown as a plain gallery, up to the free photo limit; more albums, and
 * more photos, are premium (see Event::photoLimit(), canCreateAlbum()).
 *
 * @property int $id
 * @property int $event_id
 * @property string $name
 * @property int $position
 * @property array<int, string>|null $photos paths on the public disk, in the host's order
 * @property string|null $cover_photo one of the photos; without it, the first is the cover
 * @property-read Event $event
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'position', 'photos', 'cover_photo'])]
class EventAlbum extends Model
{
    /** @use HasFactory<EventAlbumFactory> */
    use HasFactory;

    /** The album every event's photos start in (and the old gallery went to). */
    public const DEFAULT_NAME = 'Galeria';

    protected static function booted(): void
    {
        static::creating(function (EventAlbum $album): void {
            if (! $album->event->canCreateAlbum()) {
                throw ValidationException::withMessages([
                    'name' => 'Mais de um álbum faz parte do Premium. No plano gratuito, as fotos ficam numa galeria só.',
                ]);
            }
        });

        static::saving(function (EventAlbum $album): void {
            $album->photos = array_values(array_unique(array_filter($album->photos ?? [], 'is_string'))) ?: null;

            if (! in_array($album->cover_photo, $album->photoPaths(), true)) {
                $album->cover_photo = null;
            }

            $album->guardPhotoLimit();
        });

        // New photos get their light copies (see EventGalleryService).
        static::saved(function (EventAlbum $album): void {
            if (filled($album->photos) && ($album->wasRecentlyCreated || $album->wasChanged('photos'))) {
                app(EventGalleryService::class)->prepareLater($album->event);
            }
        });
    }

    /**
     * The free limit counts the photos of the whole event. What an event
     * already has is kept (it may have had more before the limit, or while
     * it was premium): only going further past the limit is refused.
     */
    protected function guardPhotoLimit(): void
    {
        $limit = $this->event->photoLimit();

        if ($limit === null) {
            return;
        }

        $others = $this->event->photoCount(except: $this);
        $before = $others + count($this->exists ? (array) ($this->getOriginal('photos') ?? []) : []);
        $after = $others + count($this->photoPaths());

        if ($after > $limit && $after > $before) {
            throw ValidationException::withMessages([
                'photos' => self::limitMessage($limit),
            ]);
        }
    }

    public static function limitMessage(int $limit): string
    {
        return "O plano gratuito permite até {$limit} fotos por evento. Remova alguma foto ou contrate o Premium para ter álbuns e fotos sem limite.";
    }

    protected function casts(): array
    {
        return [
            'photos' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return list<string>
     */
    public function photoPaths(): array
    {
        return array_values($this->photos ?? []);
    }

    /** The chosen cover, or the first photo. */
    public function coverPath(): ?string
    {
        return $this->cover_photo ?? $this->photoPaths()[0] ?? null;
    }

    /**
     * The photos with the cover first: the order of the fanned card.
     *
     * @return list<string>
     */
    public function photosCoverFirst(): array
    {
        $cover = $this->coverPath();

        return $cover === null ? [] : [$cover, ...array_values(array_diff($this->photoPaths(), [$cover]))];
    }

    /**
     * How a photo is addressed in its download link: stable while the host
     * reorders the album, and doesn't reveal the file's path.
     */
    public static function photoKey(string $path): string
    {
        return substr(sha1($path), 0, 16);
    }

    public function findPhoto(string $key): ?string
    {
        foreach ($this->photoPaths() as $path) {
            if (hash_equals(self::photoKey($path), $key)) {
                return $path;
            }
        }

        return null;
    }
}
