<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * How the RSVP form behaves. Only takes effect while the event has the
 * guest list premium feature — see Event::rsvpRequiredFields().
 *
 * @property int $id
 * @property int $event_id
 * @property bool $collect_companions
 * @property array<int, string>|null $required_fields
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['collect_companions', 'required_fields'])]
class EventRsvpSetting extends Model
{
    use LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'collect_companions' => false,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('event')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn () => 'atualizou o formulário de presença');
    }

    protected function casts(): array
    {
        return [
            'collect_companions' => 'boolean',
            'required_fields' => 'array',
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
