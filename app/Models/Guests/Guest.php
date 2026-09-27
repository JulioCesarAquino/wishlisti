<?php

namespace App\Models\Guests;

use App\Models\Concerns\HasContactDetails;
use App\Models\Events\Event;
use App\Models\Orders\Order;
use Database\Factories\Guests\GuestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $event_id
 * @property int|null $companion_of_guest_id
 * @property string $identifier
 * @property string $name
 * @property string|null $whatsapp
 * @property string|null $email
 * @property string|null $cpf
 * @property string|null $rsvp_status
 * @property int|null $rsvp_guests_count
 * @property Carbon|null $rsvp_responded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['event_id', 'companion_of_guest_id', 'name', 'whatsapp', 'email', 'cpf', 'rsvp_status', 'rsvp_guests_count', 'rsvp_responded_at'])]
class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasContactDetails, HasFactory;

    public const RSVP_CONFIRMED = 'confirmed';

    public const RSVP_DECLINED = 'declined';

    /**
     * Contact fields a host can make mandatory on the RSVP form (the name is
     * always mandatory). Each one doubles as a way to recognise the same
     * person across submissions.
     */
    public const CONTACT_FIELDS = ['whatsapp', 'email', 'cpf'];

    protected static function booted(): void
    {
        static::creating(function (Guest $guest): void {
            $guest->identifier ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'rsvp_guests_count' => 'integer',
            'rsvp_responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The guest who listed this person as someone coming with them. Null for
     * anyone who confirmed on their own.
     *
     * @return BelongsTo<Guest, $this>
     */
    public function companionOf(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'companion_of_guest_id');
    }

    /**
     * @return HasMany<Guest, $this>
     */
    public function companions(): HasMany
    {
        return $this->hasMany(Guest::class, 'companion_of_guest_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public static function cookieName(Event $event): string
    {
        return "wishlist_guest_{$event->id}";
    }
}
