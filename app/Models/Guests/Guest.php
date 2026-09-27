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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

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
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['event_id', 'companion_of_guest_id', 'name', 'whatsapp', 'email', 'cpf', 'rsvp_status', 'rsvp_guests_count', 'rsvp_responded_at'])]
class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasContactDetails, HasFactory, LogsActivity, SoftDeletes;

    /**
     * Only removals and restores are audited — RSVPs happen all the time
     * and are the guest's own doing, not the host's.
     *
     * @var array<int, string>
     */
    protected static array $recordEvents = ['deleted', 'restored'];

    /** Name left on a guest whose personal data was erased. */
    public const ANONYMIZED_NAME = 'Convidado removido';

    public const RSVP_CONFIRMED = 'confirmed';

    public const RSVP_DECLINED = 'declined';

    /**
     * Contact fields a host can make mandatory on the RSVP form (the name is
     * always mandatory). Each one doubles as a way to recognise the same
     * person across submissions.
     */
    public const CONTACT_FIELDS = ['whatsapp', 'email', 'cpf'];

    public function getActivitylogOptions(): LogOptions
    {
        // No attributes: the audit trail must not keep a copy of personal
        // data (it would survive an anonymization).
        return LogOptions::defaults()
            ->useLogName('guest')
            ->logOnly([])
            ->setDescriptionForEvent(fn (string $event) => match ($event) {
                'deleted' => $this->isForceDeleting() ? 'excluiu definitivamente o convidado' : 'moveu o convidado para a lixeira',
                'restored' => 'restaurou o convidado',
                default => $event,
            });
    }

    protected static function booted(): void
    {
        static::creating(function (Guest $guest): void {
            $guest->identifier ??= (string) Str::uuid();
        });

        // Keeps the headcounts right when a guest goes to (or comes back
        // from) the trash: a companion leaves or rejoins the headcount of
        // whoever listed them, and a guest takes their companions along.
        static::softDeleted(function (Guest $guest): void {
            $guest->companionOf?->adjustHeadcount(-1);
            $guest->companions()->get()->each->delete();
        });

        static::restoring(function (Guest $guest): void {
            $deletedAt = $guest->getOriginal('deleted_at');

            if ($deletedAt) {
                $guest->companions()
                    ->onlyTrashed()
                    ->whereBetween('deleted_at', [$deletedAt, Carbon::parse($deletedAt)->addMinute()])
                    ->get()
                    ->each->restore();
            }
        });

        static::restored(function (Guest $guest): void {
            $guest->companionOf?->adjustHeadcount(1);
        });
    }

    private function adjustHeadcount(int $by): void
    {
        if ($this->rsvp_status !== self::RSVP_CONFIRMED || $this->rsvp_guests_count === null) {
            return;
        }

        $this->forceFill(['rsvp_guests_count' => max(1, $this->rsvp_guests_count + $by)])->saveQuietly();
    }

    /**
     * Guests with a paid order are part of the event's financial history,
     * so they can't be deleted — only anonymized.
     */
    public function hasPaidOrders(): bool
    {
        return $this->orders()->where('status', Order::STATUS_PAID)->exists();
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
