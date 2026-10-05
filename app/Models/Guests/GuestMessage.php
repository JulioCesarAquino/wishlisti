<?php

namespace App\Models\Guests;

use App\Models\Events\Event;
use App\Models\User;
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
 * @property string|null $author_name
 * @property string $message
 * @property bool $is_anonymous
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['event_id', 'guest_id', 'author_name', 'message', 'is_anonymous', 'approved_at'])]
class GuestMessage extends Model
{
    /** @use HasFactory<GuestMessageFactory> */
    use HasFactory;

    /** What the page and the host see in place of an anonymous author. */
    public const ANONYMOUS_NAME = 'Anônimo';

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'is_anonymous' => 'boolean',
        ];
    }

    /**
     * The author as the viewer may see it: an anonymous message shows as
     * "Anônimo" on the page and to the host; the admin still sees the name
     * the guest gave, if any.
     */
    public function authorNameFor(?User $viewer): string
    {
        if (! $this->is_anonymous) {
            return (string) $this->author_name;
        }

        return $viewer?->isAdmin() && filled($this->author_name)
            ? "{$this->author_name} (anônimo)"
            : self::ANONYMOUS_NAME;
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
