<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The event's Mercado Pago credentials. Kept apart from the event and
 * deliberately without activity logging, so the secrets never end up in
 * the audit trail.
 *
 * @property int $id
 * @property int $event_id
 * @property string|null $mp_access_token
 * @property string|null $mp_public_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['mp_access_token', 'mp_public_key'])]
#[Hidden(['mp_access_token', 'mp_public_key'])]
class EventPaymentSetting extends Model
{
    protected function casts(): array
    {
        return [
            'mp_access_token' => 'encrypted',
            'mp_public_key' => 'encrypted',
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
