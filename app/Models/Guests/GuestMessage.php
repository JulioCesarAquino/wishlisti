<?php

namespace App\Models\Guests;

use App\Models\Events\Event;
use Database\Factories\Guests\GuestMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A message a guest left on the event's guestbook (premium). It only shows
 * on the public page once the host approves it.
 *
 * @property int $id
 * @property int $event_id
 * @property int|null $guest_id
 * @property string $author_name
 * @property string $message
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['event_id', 'guest_id', 'author_name', 'message', 'approved_at'])]
class GuestMessage extends Model
{
    /** @use HasFactory<GuestMessageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<GuestMessage>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->whereNotNull('approved_at');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class)->withTrashed();
    }
}
