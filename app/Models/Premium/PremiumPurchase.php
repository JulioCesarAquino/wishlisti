<?php

namespace App\Models\Premium;

use App\Models\Events\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A host buying the premium plan for one of their events, paid to the
 * platform's Mercado Pago account.
 *
 * @property int $id
 * @property int $event_id
 * @property int $user_id
 * @property string $amount
 * @property string $status
 * @property string|null $payment_id
 * @property string|null $payment_method
 * @property string|null $payment_url
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['event_id', 'user_id', 'amount', 'status', 'payment_id', 'payment_method', 'payment_url', 'paid_at'])]
class PremiumPurchase extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<FeatureGrant, $this>
     */
    public function featureGrants(): HasMany
    {
        return $this->hasMany(FeatureGrant::class);
    }

    public function externalReference(): string
    {
        return "premium-{$this->id}";
    }

    public static function idFromExternalReference(?string $reference): ?int
    {
        return preg_match('/^premium-(\d+)$/', (string) $reference, $matches) ? (int) $matches[1] : null;
    }
}
