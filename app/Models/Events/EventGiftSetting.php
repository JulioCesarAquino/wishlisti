<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * How guests can give gifts at this event.
 *
 * @property int $id
 * @property int $event_id
 * @property bool $allow_in_person
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['allow_in_person'])]
class EventGiftSetting extends Model
{
    use LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'allow_in_person' => true,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('event')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn () => 'atualizou as opções de presente');
    }

    protected function casts(): array
    {
        return [
            'allow_in_person' => 'boolean',
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
