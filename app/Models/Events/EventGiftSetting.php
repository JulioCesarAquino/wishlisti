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
 * @property string $display_mode
 * @property string|null $gift_message
 * @property bool $allow_in_person
 * @property bool $allow_free_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['display_mode', 'gift_message', 'allow_in_person', 'allow_free_amount'])]
class EventGiftSetting extends Model
{
    use LogsActivity;

    /** The gift list in its own menu section, as a proper list. */
    public const DISPLAY_LIST = 'list';

    /**
     * "Se quiser presentear": out of the menu, tucked at the end of the home
     * section, without sold-out badges, counters or prices in focus.
     */
    public const DISPLAY_DISCREET = 'discreet';

    /** No gift list at all — just the host's message. */
    public const DISPLAY_NONE = 'none';

    public const DEFAULT_GIFT_MESSAGE = 'Sua presença é o nosso presente.';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'display_mode' => self::DISPLAY_LIST,
        'allow_in_person' => true,
        'allow_free_amount' => false,
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
            'allow_free_amount' => 'boolean',
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
