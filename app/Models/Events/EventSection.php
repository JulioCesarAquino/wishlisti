<?php

namespace App\Models\Events;

use App\Enums\Events\PageSection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Whether a tab of the event's public page is on, and where it sits in the
 * menu.
 *
 * @property int $id
 * @property int $event_id
 * @property PageSection $type
 * @property bool $is_active
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'is_active', 'position'])]
class EventSection extends Model
{
    use LogsActivity;

    protected $attributes = [
        'is_active' => true,
        'position' => 0,
    ];

    protected static function booted(): void
    {
        // The home tab is where the page opens: it can't be turned off.
        static::saving(function (EventSection $section): void {
            $type = $section->getAttribute('type');

            if ($type instanceof PageSection && ! $type->canBeDisabled()) {
                $section->is_active = true;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('event')
            ->logOnly(['type', 'is_active', 'position'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn () => 'atualizou as abas da página');
    }

    protected function casts(): array
    {
        return [
            'type' => PageSection::class,
            'is_active' => 'boolean',
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
}
