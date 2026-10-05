<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * How the RSVP form behaves. The fields and the companions only take effect
 * while the event has the guest list premium feature (see
 * Event::rsvpFields()); the children's age limit, always.
 *
 * @property int $id
 * @property int $event_id
 * @property bool $collect_companions
 * @property array<string, string>|null $fields field => FIELD_HIDDEN|FIELD_OPTIONAL|FIELD_REQUIRED
 * @property int|null $child_age_limit children under this age don't pay
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['collect_companions', 'fields', 'child_age_limit'])]
class EventRsvpSetting extends Model
{
    use LogsActivity;

    public const FIELD_HIDDEN = 'hidden';

    public const FIELD_OPTIONAL = 'optional';

    public const FIELD_REQUIRED = 'required';

    /** Besides the name, which is always asked for and required. */
    public const FIELDS = ['whatsapp', 'email', 'cpf', 'age'];

    /** The free form: WhatsApp to reach the guest, e-mail if they like. */
    public const DEFAULT_FIELDS = [
        'whatsapp' => self::FIELD_REQUIRED,
        'email' => self::FIELD_OPTIONAL,
        'cpf' => self::FIELD_HIDDEN,
        'age' => self::FIELD_HIDDEN,
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'collect_companions' => false,
    ];

    /**
     * The fields of the form as it was before each field had a mode: those
     * listed required; WhatsApp and e-mail otherwise optional; the CPF and
     * the age not asked.
     *
     * @param  array<int, string>  $required
     * @return array<string, string>
     */
    public static function fieldsRequiring(array $required): array
    {
        return [
            'whatsapp' => in_array('whatsapp', $required, true) ? self::FIELD_REQUIRED : self::FIELD_OPTIONAL,
            'email' => in_array('email', $required, true) ? self::FIELD_REQUIRED : self::FIELD_OPTIONAL,
            'cpf' => in_array('cpf', $required, true) ? self::FIELD_REQUIRED : self::FIELD_HIDDEN,
            'age' => in_array('age', $required, true) ? self::FIELD_REQUIRED : self::FIELD_HIDDEN,
        ];
    }

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
            'fields' => 'array',
            'child_age_limit' => 'integer',
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
