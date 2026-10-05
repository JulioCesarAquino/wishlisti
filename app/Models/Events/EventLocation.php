<?php

namespace App\Models\Events;

use Database\Factories\Events\EventLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One of the places the event happens at ("Cerimônia", "Festa"…), with a
 * public link of its own: /{event}/localizacao/{slug}.
 *
 * @property int $id
 * @property int $event_id
 * @property string $name
 * @property string|null $start_time HH:MM:SS, Brasília time
 * @property string $slug
 * @property string $address
 * @property string|null $maps_url
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int $position
 * @property-read Event $event
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'start_time', 'slug', 'address', 'maps_url', 'latitude', 'longitude', 'position'])]
class EventLocation extends Model
{
    /** @use HasFactory<EventLocationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // A blank or taken slug is derived from the name, unique within the
        // event — the link of a location already shared never changes on
        // its own, only if the host edits the slug.
        static::saving(function (EventLocation $location): void {
            $location->slug = $location->uniqueSlug(Str::slug(filled($location->slug) ? $location->slug : $location->name) ?: 'local');
        });
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 1;

        while (static::where('event_id', $this->event_id)
            ->where('slug', $slug)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function publicUrl(): string
    {
        return route('events.location', ['event' => $this->event->slug, 'location' => $this->slug]);
    }
}
