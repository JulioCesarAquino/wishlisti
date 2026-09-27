<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * How the event's public page looks.
 *
 * @property int $id
 * @property int $event_id
 * @property string|null $primary_color
 * @property string|null $secondary_color
 * @property string|null $font_color_primary
 * @property string|null $font_color_secondary
 * @property string|null $font_family
 * @property int $cover_effect_intensity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'primary_color', 'secondary_color', 'font_color_primary', 'font_color_secondary',
    'font_family', 'cover_effect_intensity',
])]
class EventAppearance extends Model
{
    use LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'cover_effect_intensity' => 100,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('event')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn () => 'atualizou a aparência do evento');
    }

    protected function casts(): array
    {
        return [
            'cover_effect_intensity' => 'integer',
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
