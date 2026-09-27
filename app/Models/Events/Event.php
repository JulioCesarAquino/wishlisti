<?php

namespace App\Models\Events;

use App\Enums\Premium\Feature;
use App\Models\Catalog\EventProduct;
use App\Models\Concerns\HasFeatureGrants;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use App\Models\User;
use Database\Factories\Events\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property string $type
 * @property string $title
 * @property Carbon|null $event_date
 * @property string|null $cover_image
 * @property array<int, string>|null $gallery
 * @property string|null $description
 * @property string|null $story
 * @property string|null $address
 * @property float|null $latitude
 * @property float|null $longitude
 * @property bool $is_published
 * @property Carbon|null $archived_at
 * @property Carbon|null $deleted_at
 * @property int $visits_count
 * @property-read EventAppearance $appearance
 * @property-read EventRsvpSetting $rsvpSettings
 * @property-read EventPaymentSetting $paymentSettings
 * @property-read EventGiftSetting $giftSettings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'slug', 'type', 'title', 'event_date', 'cover_image',
    'gallery', 'description', 'story', 'address', 'latitude', 'longitude',
    'is_published', 'archived_at',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    use HasFeatureGrants, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('event')
            ->logOnly([
                'title', 'type', 'event_date', 'description', 'story', 'cover_image', 'gallery',
                'address', 'latitude', 'longitude', 'is_published', 'archived_at',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => match ($event) {
                'created' => 'criou o evento',
                'updated' => 'atualizou o evento',
                'deleted' => $this->isForceDeleting() ? 'excluiu definitivamente o evento' : 'moveu o evento para a lixeira',
                'restored' => 'restaurou o evento',
                default => $event,
            });
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            if (blank($event->slug)) {
                $event->slug = $event->generateUniqueSlug($event->title);
            }
        });
    }

    protected function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 1;

        // Events in the trash still hold their slug (it's unique in the DB).
        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'gallery' => 'array',
            'is_published' => 'boolean',
            'archived_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Events created before these settings existed (or by factories) may
     * have no row yet, so each falls back to an unsaved one holding the
     * defaults.
     *
     * @return HasOne<EventAppearance, $this>
     */
    public function appearance(): HasOne
    {
        return $this->hasOne(EventAppearance::class)->withDefault();
    }

    /**
     * @return HasOne<EventRsvpSetting, $this>
     */
    public function rsvpSettings(): HasOne
    {
        return $this->hasOne(EventRsvpSetting::class)->withDefault();
    }

    /**
     * @return HasOne<EventPaymentSetting, $this>
     */
    public function paymentSettings(): HasOne
    {
        return $this->hasOne(EventPaymentSetting::class)->withDefault();
    }

    /**
     * @return HasOne<EventGiftSetting, $this>
     */
    public function giftSettings(): HasOne
    {
        return $this->hasOne(EventGiftSetting::class)->withDefault();
    }

    /**
     * @return HasMany<EventProduct, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(EventProduct::class);
    }

    /**
     * @return HasMany<Guest, $this>
     */
    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Contact fields (besides the always-required name) the RSVP form
     * demands from the guest and from each companion. Customising them is a
     * premium feature; without it the form keeps its original shape, where
     * WhatsApp is the one mandatory contact.
     *
     * @return array<int, string>
     */
    public function rsvpRequiredFields(): array
    {
        $fields = array_values(array_intersect(Guest::CONTACT_FIELDS, $this->rsvpSettings->required_fields ?? []));

        return $this->hasFeature(Feature::GuestList) && $fields !== [] ? $fields : ['whatsapp'];
    }

    /**
     * Whether the RSVP form asks for each companion's details instead of
     * just a headcount — also premium-only.
     */
    public function collectsRsvpCompanions(): bool
    {
        return $this->hasFeature(Feature::GuestList) && $this->rsvpSettings->collect_companions;
    }

    public function coverImageUrl(): ?string
    {
        return $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null;
    }

    /**
     * @return array<int, string>
     */
    public function galleryUrls(): array
    {
        return array_map(
            fn (string $path): string => Storage::disk('public')->url($path),
            $this->gallery ?? [],
        );
    }

    public function isViewableBy(?User $user): bool
    {
        if ($this->is_published && ! $this->isArchived()) {
            return true;
        }

        return $user && ($user->isAdmin() || $user->id === $this->user_id);
    }

    /**
     * Guests can pay for gifts online: a premium feature, and it needs the
     * host's Mercado Pago credentials to be set up.
     */
    public function acceptsOnlineGifts(): bool
    {
        return $this->hasFeature(Feature::Payments)
            && filled($this->paymentSettings->mp_access_token)
            && filled($this->paymentSettings->mp_public_key);
    }

    /**
     * Guests can reserve gifts and hand them over in person. Always the case
     * on free events (it's their only way to give); premium events can
     * turn it off to take online gifts only.
     */
    public function acceptsInPersonGifts(): bool
    {
        return ! $this->hasFeature(Feature::Payments) || $this->giftSettings->allow_in_person;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Events with paid orders are part of the host's financial history:
     * the host can archive them, and only the admin can delete them.
     */
    public function hasPaidOrders(): bool
    {
        return $this->orders()->where('status', Order::STATUS_PAID)->exists();
    }

    public function visitCookieName(): string
    {
        return "wishlist_visited_{$this->id}";
    }

    public function shouldCountVisitFor(?User $user): bool
    {
        if (! $this->is_published || $this->isArchived()) {
            return false;
        }

        return ! ($user && ($user->isAdmin() || $user->id === $this->user_id));
    }
}
