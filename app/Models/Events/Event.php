<?php

namespace App\Models\Events;

use App\Enums\Events\PageSection;
use App\Enums\Premium\Feature;
use App\Models\Catalog\EventProduct;
use App\Models\Concerns\HasFeatureGrants;
use App\Models\Guests\Guest;
use App\Models\Guests\GuestMessage;
use App\Models\Orders\Order;
use App\Models\Premium\PremiumPurchase;
use App\Models\User;
use App\Services\Premium\PremiumValidityService;
use Database\Factories\Events\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
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
 * @property string|null $event_time HH:MM:SS, Brasília time
 * @property string|null $cover_image
 * @property string|null $share_image
 * @property array<int, string>|null $gallery
 * @property string|null $description
 * @property string|null $story_title the story section's title, when the host wrote one
 * @property string|null $story
 * @property bool $is_published
 * @property Carbon|null $archived_at
 * @property Carbon|null $deleted_at
 * @property int $visits_count
 * @property-read EventAppearance $appearance
 * @property-read EventRsvpSetting $rsvpSettings
 * @property-read EventPaymentSetting $paymentSettings
 * @property-read EventGiftSetting $giftSettings
 * @property-read Collection<int, EventLocation> $locations
 * @property-read Collection<int, EventSection> $sections
 * @property-read Collection<int, User> $coHosts
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'slug', 'type', 'title', 'event_date', 'event_time', 'cover_image', 'share_image',
    'gallery', 'description', 'story_title', 'story', 'is_published', 'archived_at',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    use HasFeatureGrants, LogsActivity, SoftDeletes;

    public const TYPE_LABELS = [
        'casamento' => 'Casamento',
        'noivado' => 'Noivado',
        'cha_bebe' => 'Chá de bebê',
        'cha_revelacao' => 'Chá revelação',
        'cha_panela' => 'Chá de panela',
        'aniversario' => 'Aniversário',
        'batizado' => 'Batizado',
        'formatura' => 'Formatura',
        'churrasco' => 'Churrasco',
        'futebol' => 'Futebol',
        'confraternizacao' => 'Confraternização',
        'outro' => 'Outro',
    ];

    /**
     * The title of the story section, by type, while the host doesn't write
     * one: "Nossa história" only fits a couple.
     */
    public const STORY_TITLES = [
        'casamento' => 'Nossa história',
        'noivado' => 'Nossa história',
        'cha_bebe' => 'Esperando por você',
        'cha_revelacao' => 'Esperando por você',
        'cha_panela' => 'Nossa história',
        'aniversario' => 'Sobre o aniversário',
        'batizado' => 'Sobre o batizado',
        'formatura' => 'Nossa trajetória',
    ];

    /** Dates and times of events are typed, and shown, in Brasília time. */
    public const TIMEZONE = 'America/Sao_Paulo';

    /**
     * "16h", "18h30" — how a time ("16:00:00", "18:30") reads in Portuguese.
     */
    public static function formatTime(?string $time): ?string
    {
        if (blank($time)) {
            return null;
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $minutes === 0 ? "{$hours}h" : sprintf('%dh%02d', $hours, $minutes);
    }

    /** "Casamento", "Churrasco"… — "Evento" when it's another kind. */
    public function typeLabel(): string
    {
        return $this->type === 'outro' ? 'Evento' : (self::TYPE_LABELS[$this->type] ?? 'Evento');
    }

    /** The story section's title: the host's, or the one for the type. */
    public function storyTitle(): string
    {
        return filled($this->story_title) ? $this->story_title : self::defaultStoryTitle($this->type);
    }

    public static function defaultStoryTitle(?string $type): string
    {
        return self::STORY_TITLES[$type] ?? 'Sobre o evento';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('event')
            ->logOnly([
                'title', 'type', 'event_date', 'event_time', 'description', 'story_title', 'story', 'cover_image', 'share_image', 'gallery',
                'is_published', 'archived_at',
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

        // The Premium ends some days after the event's date.
        static::updated(function (Event $event): void {
            if ($event->wasChanged('event_date')) {
                app(PremiumValidityService::class)->eventDateChanged($event, byAdmin: (bool) auth()->user()?->isAdmin());
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

    /**
     * When the event starts, in Brasília time: what the countdown counts down
     * to. Midnight of the day while the host hasn't set a time.
     */
    public function startsAt(): ?Carbon
    {
        if (! $this->event_date) {
            return null;
        }

        return Carbon::parse($this->event_date->toDateString().' '.($this->event_time ?? '00:00:00'), self::TIMEZONE);
    }

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'gallery' => 'array',
            'is_published' => 'boolean',
            'archived_at' => 'datetime',
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
     * Users who manage the event alongside its owner.
     *
     * @return BelongsToMany<User, $this>
     */
    public function coHosts(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user')->withTimestamps();
    }

    /**
     * @return HasMany<PremiumPurchase, $this>
     */
    public function premiumPurchases(): HasMany
    {
        return $this->hasMany(PremiumPurchase::class);
    }

    /**
     * Where the event happens, in the order the host chose.
     *
     * @return HasMany<EventLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(EventLocation::class)->orderBy('position')->orderBy('id');
    }

    /**
     * The tabs the host configured (see pageSections()).
     *
     * @return HasMany<EventSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(EventSection::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Every tab of the public page, in the host's order. Tabs without a saved
     * row (all of them, on events nobody configured) are on, after the saved
     * ones, in the default order.
     *
     * @return SupportCollection<int, EventSection>
     */
    public function pageSections(): SupportCollection
    {
        $saved = $this->sections->keyBy(fn (EventSection $section) => $section->type->value);
        $next = (int) $this->sections->max('position') + ($saved->isEmpty() ? 0 : 1);

        return collect(PageSection::cases())
            ->map(function (PageSection $type) use ($saved, &$next): EventSection {
                return $saved->get($type->value) ?? new EventSection([
                    'type' => $type,
                    'is_active' => true,
                    'position' => $next++,
                ]);
            })
            ->sortBy('position')
            ->values();
    }

    /**
     * Saves a row for every tab, so the host can configure all of them.
     */
    public function ensureSections(): void
    {
        $this->pageSections()
            ->reject(fn (EventSection $section) => $section->exists)
            ->each(fn (EventSection $section) => $this->sections()->save($section));

        $this->unsetRelation('sections');
    }

    /**
     * The tabs in the page's menu: the ones the host left on that also have
     * something to show. The page opens on the first. Should none be left
     * (the only tabs on had nothing to show), the home tab stands in.
     *
     * @return array<int, PageSection>
     */
    public function visibleSections(): array
    {
        $sections = $this->pageSections()
            ->filter(fn (EventSection $section) => $section->is_active && $this->hasContentFor($section->type))
            ->map(fn (EventSection $section) => $section->type)
            ->values()
            ->all();

        return $sections ?: [PageSection::Home];
    }

    public function showsSection(PageSection $section): bool
    {
        return in_array($section, $this->visibleSections(), true);
    }

    /**
     * Whether the host left the tab on — regardless of it having anything
     * to show.
     */
    public function isSectionEnabled(PageSection $section): bool
    {
        $row = $this->pageSections()->first(fn (EventSection $row) => $row->type === $section);

        return $row === null || $row->is_active;
    }

    protected function hasContentFor(PageSection $section): bool
    {
        return match ($section) {
            // In the other display modes, gifts live in the home tab.
            PageSection::Gifts => $this->giftDisplayMode() === EventGiftSetting::DISPLAY_LIST,
            // After the Premium, the approved messages stay, as a keepsake.
            PageSection::Guestbook => $this->hasFeature(Feature::Guestbook) || $this->messages()->approved()->exists(),
            PageSection::Location => $this->locations->isNotEmpty(),
            default => true,
        };
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
     * @return HasMany<GuestMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(GuestMessage::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * What the RSVP form asks of the guest and of each companion, besides
     * the always-required name: each field hidden, optional or required.
     * Customising it is a premium feature; without it the form keeps its
     * original shape (WhatsApp required, e-mail optional).
     *
     * @return array<string, string>
     */
    public function rsvpFields(): array
    {
        $chosen = $this->hasFeature(Feature::GuestList) ? ($this->rsvpSettings->fields ?? []) : [];
        $fields = EventRsvpSetting::DEFAULT_FIELDS;

        foreach (EventRsvpSetting::FIELDS as $field) {
            if (in_array($chosen[$field] ?? null, [EventRsvpSetting::FIELD_HIDDEN, EventRsvpSetting::FIELD_OPTIONAL, EventRsvpSetting::FIELD_REQUIRED], true)) {
                $fields[$field] = $chosen[$field];
            }
        }

        return $fields;
    }

    /**
     * The contact fields alone (no age) — how a guest is identified on the
     * gift cart too.
     *
     * @return array<string, string>
     */
    public function rsvpContactFields(): array
    {
        return array_intersect_key($this->rsvpFields(), array_flip(Guest::CONTACT_FIELDS));
    }

    /**
     * The contact fields the form requires.
     *
     * @return array<int, string>
     */
    public function rsvpRequiredFields(): array
    {
        return array_keys(array_filter($this->rsvpContactFields(), fn (string $mode) => $mode === EventRsvpSetting::FIELD_REQUIRED));
    }

    /**
     * "Children under X don't pay" — any event, free or premium.
     */
    public function childAgeLimit(): ?int
    {
        return $this->rsvpSettings->child_age_limit ?: null;
    }

    /**
     * Whether the form asks how many of the guest's party are children:
     * whenever there's an age limit and each person's age can't tell —
     * companions not listed one by one, or no age asked.
     */
    public function asksRsvpChildrenCount(): bool
    {
        return $this->childAgeLimit() !== null
            && ! ($this->collectsRsvpCompanions() && $this->rsvpFields()['age'] !== EventRsvpSetting::FIELD_HIDDEN);
    }

    /**
     * Who's coming, for the host (and the buffet): people confirmed and, with
     * an age limit, how many of them are children under it — from each
     * person's age, or from the count a guest gave for their party.
     *
     * @return array{people: int, children: int|null, paying: int|null}
     */
    public function rsvpHeadcount(): array
    {
        $confirmed = $this->guests()->where('rsvp_status', Guest::RSVP_CONFIRMED)->get();
        $people = (int) $confirmed->sum('rsvp_guests_count');
        $limit = $this->childAgeLimit();

        if ($limit === null) {
            return ['people' => $people, 'children' => null, 'paying' => null];
        }

        $isChild = fn (Guest $guest): bool => $guest->age !== null && $guest->age < $limit;

        $children = $confirmed->sum(fn (Guest $guest): int => $guest->companion_of_guest_id === null && $guest->rsvp_children_count !== null
            ? $guest->rsvp_children_count
            : (int) $isChild($guest));

        return ['people' => $people, 'children' => (int) $children, 'paying' => max(0, $people - (int) $children)];
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

        return $this->isManagedBy($user);
    }

    /**
     * Who runs the event: the admin, its owner and its co-hosts. Co-hosts
     * can do everything but add or remove co-hosts and trash the event.
     */
    public function isManagedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isAdmin() || $this->isOwnedBy($user) || $this->coHosts()->whereKey($user->getKey())->exists();
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $user->id === $this->user_id;
    }

    /**
     * Events the user runs, as owner or co-host (not every event for the
     * admin: callers keep their own admin check).
     *
     * @param  Builder<Event>  $query
     */
    public function scopeManagedBy(Builder $query, User|int|string|null $user): void
    {
        $userId = $user instanceof User ? $user->id : $user;

        $query->where(fn (Builder $query) => $query
            ->where('user_id', $userId)
            ->orWhereHas('coHosts', fn (Builder $coHosts) => $coHosts->whereKey($userId)));
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

    /**
     * How many gifts the list can hold: unlimited with the premium feature,
     * config('premium.free_gift_limit') otherwise.
     */
    public function giftLimit(): ?int
    {
        return $this->hasFeature(Feature::FullGiftList) ? null : (int) config('premium.free_gift_limit');
    }

    public function canAddGifts(int $count = 1): bool
    {
        $limit = $this->giftLimit();

        return $limit === null || $this->products()->count() + $count <= $limit;
    }

    public function giftDisplayMode(): string
    {
        return $this->giftSettings->display_mode ?: EventGiftSetting::DISPLAY_LIST;
    }

    /**
     * Guests can contribute any amount without picking an item: goes
     * through the online checkout, so it needs online gifts.
     */
    public function acceptsFreeAmount(): bool
    {
        return $this->acceptsOnlineGifts() && $this->giftSettings->allow_free_amount;
    }

    /**
     * Items can be given at all (they're hidden in the "no gifts" mode).
     */
    public function showsGiftItems(): bool
    {
        return $this->giftDisplayMode() !== EventGiftSetting::DISPLAY_NONE;
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
        return "wishlisti_visited_{$this->id}";
    }

    public function shouldCountVisitFor(?User $user): bool
    {
        if (! $this->is_published || $this->isArchived()) {
            return false;
        }

        return ! $this->isManagedBy($user);
    }
}
