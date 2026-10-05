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
 * @property string|null $button_color
 * @property string|null $font_color_primary
 * @property string|null $font_color_secondary
 * @property string|null $font_family
 * @property int $cover_effect_intensity
 * @property bool $show_location_shortcut
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'primary_color', 'secondary_color', 'button_color', 'font_color_primary', 'font_color_secondary',
    'font_family', 'cover_effect_intensity', 'show_location_shortcut',
])]
class EventAppearance extends Model
{
    use LogsActivity;

    /**
     * Ready-made button colors offered in the panel: lively, and dark enough
     * for white text. Any other color works too — the page picks white or
     * dark text for it.
     */
    public const BUTTON_COLOR_PRESETS = [
        '#2f855a' => 'Verde',
        '#2b6cb0' => 'Azul',
        '#c2410c' => 'Coral',
        '#975a16' => 'Dourado',
        '#8b2343' => 'Vinho',
    ];

    /** Used while the host hasn't picked a button color. */
    public const DEFAULT_BUTTON_COLOR = '#2f855a';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'cover_effect_intensity' => 100,
        'show_location_shortcut' => true,
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
            'show_location_shortcut' => 'boolean',
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
