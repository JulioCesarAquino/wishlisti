<?php

namespace App\Services\Premium;

use App\Models\Events\Event;
use App\Models\Premium\FeatureGrant;
use App\Models\Premium\PremiumPurchase;
use App\Support\PlatformSettings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The Premium is bought for an event on a date: its event features end
 * some days (the grace period, set by the admin) after that date. The host
 * can move the date only so far from the one it was bought for, and moving
 * it never brings back a Premium that has already ended — otherwise one
 * purchase could serve party after party.
 *
 * The host features (contacts) are the host's, not the event's, and don't
 * end.
 */
class PremiumValidityService
{
    /** The end of the grace period after an event on this date (Brasília). */
    public function expiresAt(CarbonInterface $eventDate): CarbonInterface
    {
        return Carbon::parse($eventDate->toDateString(), Event::TIMEZONE)
            ->addDays(PlatformSettings::premiumGraceDays())
            ->endOfDay()
            ->utc();
    }

    /** The paid purchase the event's Premium comes from, if any. */
    public function purchase(Event $event): ?PremiumPurchase
    {
        return $event->premiumPurchases()
            ->where('status', PremiumPurchase::STATUS_PAID)
            ->whereNotNull('event_date')
            ->latest('id')
            ->first();
    }

    /**
     * When the event's purchased Premium ends — or ended. Null without one.
     */
    public function endsAt(Event $event): ?CarbonInterface
    {
        return $this->eventGrants($event)->max('expires_at');
    }

    /**
     * The dates the host may set while the Premium lasts: within the window
     * around the date it was bought for. Null when there's nothing to hold
     * (no Premium bought, or it has ended).
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}|null
     */
    public function allowedDates(Event $event): ?array
    {
        $purchase = $this->purchase($event);

        if (! $purchase?->event_date || ! $this->eventGrants($event)->contains(fn (FeatureGrant $grant) => $grant->isActive())) {
            return null;
        }

        $window = PlatformSettings::premiumDateWindowDays();

        return [
            $purchase->event_date->copy()->subDays($window)->startOfDay(),
            $purchase->event_date->copy()->addDays($window)->startOfDay(),
        ];
    }

    /**
     * The event's date changed. The host's change (already held within the
     * window) moves the end along, but never revives an ended Premium. The
     * admin's — a postponement agreed with them — becomes the date the
     * Premium is for, and can revive it.
     */
    public function eventDateChanged(Event $event, bool $byAdmin): void
    {
        $purchase = $this->purchase($event);

        if (! $purchase) {
            return;
        }

        if ($byAdmin && $event->event_date) {
            $purchase->update(['event_date' => $event->event_date]);
        }

        $this->sync($event, revive: $byAdmin);
    }

    /**
     * The admin changed the grace period: every Premium ends accordingly,
     * ended ones included (a longer period brings them back).
     */
    public function syncAll(): void
    {
        Event::withTrashed()
            ->whereHas('premiumPurchases', fn ($query) => $query->where('status', PremiumPurchase::STATUS_PAID))
            ->each(fn (Event $event) => $this->sync($event, revive: true));
    }

    /**
     * Sets when the event features of the event's Premium end: the grace
     * period after the event's date (or, without one, the date it was
     * bought for).
     */
    public function sync(Event $event, bool $revive = false): void
    {
        $purchase = $this->purchase($event);
        $date = $event->event_date ?? $purchase?->event_date;

        if (! $purchase || ! $date) {
            return;
        }

        $expiresAt = $this->expiresAt($date);

        $this->eventGrants($event)
            ->filter(fn (FeatureGrant $grant) => $revive || $grant->isActive())
            ->each(fn (FeatureGrant $grant) => $grant->update(['expires_at' => $expiresAt]));

        $event->unsetRelation('featureGrants');
    }

    /**
     * @return Collection<int, FeatureGrant>
     */
    private function eventGrants(Event $event): Collection
    {
        return $event->featureGrants()
            ->where('source', FeatureGrant::SOURCE_PURCHASE)
            ->whereNotNull('premium_purchase_id')
            ->get();
    }
}
