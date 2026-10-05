<?php

namespace App\Models\Orders;

use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\User;
use Database\Factories\Orders\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property int $guest_id
 * @property string $status
 * @property string $fulfillment
 * @property bool $is_free_amount
 * @property float $total_amount
 * @property string|null $message
 * @property bool $is_anonymous
 * @property string|null $preference_id
 * @property string|null $payment_id
 * @property string|null $payment_method
 * @property string|null $payment_type
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'event_id', 'guest_id', 'status', 'fulfillment', 'is_free_amount', 'total_amount', 'message', 'is_anonymous',
    'preference_id', 'payment_id', 'payment_method', 'payment_type', 'paid_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * An online gift the guest gave up on: no payment came within
     * ABANDONED_AFTER_MINUTES of it being ordered.
     */
    public const STATUS_EXPIRED = 'expired';

    public const ABANDONED_AFTER_MINUTES = 120;

    /** An in-person gift the guest has set aside for themselves. */
    public const STATUS_RESERVED = 'reserved';

    /** An in-person gift the host marked as handed over. */
    public const STATUS_RECEIVED = 'received';

    /** Paid online through the host's Mercado Pago. */
    public const FULFILLMENT_ONLINE = 'online';

    /** Reserved on the page, handed over by the guest in person. */
    public const FULFILLMENT_IN_PERSON = 'in_person';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'fulfillment' => self::FULFILLMENT_ONLINE,
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'is_anonymous' => 'boolean',
            'is_free_amount' => 'boolean',
            'paid_at' => 'datetime',
        ];
    }

    public function isInPerson(): bool
    {
        return $this->fulfillment === self::FULFILLMENT_IN_PERSON;
    }

    public function isReservation(): bool
    {
        return $this->isInPerson() && $this->status === self::STATUS_RESERVED;
    }

    /**
     * An anonymous gift hides who gave it — and when, since the time could
     * be matched against the guest's RSVP — from the host. The admin still
     * sees everything.
     */
    public function hidesGiverFrom(?User $user): bool
    {
        return $this->is_anonymous && ! $user?->isAdmin();
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        // Orders outlive a trashed event or guest: the financial history
        // must keep showing who and what they were.
        return $this->belongsTo(Event::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class)->withTrashed();
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
