<?php

namespace App\Services\Guests;

use App\Models\Events\Event;
use App\Models\Guests\Guest;
use Illuminate\Support\Facades\DB;

class RsvpStoreService
{
    public function __construct(
        protected GuestResolveService $guestResolveService,
    ) {}

    /**
     * @param  array{name: string, whatsapp?: ?string, email?: ?string, cpf?: ?string}  $guestData
     * @param  array<int, array{name: string, whatsapp?: ?string, email?: ?string, cpf?: ?string}>  $companions
     */
    public function execute(
        Event $event,
        array $guestData,
        bool $attending,
        ?int $guestsCount,
        array $companions,
        ?string $guestIdentifier,
    ): Guest {
        return DB::transaction(function () use ($event, $guestData, $attending, $guestsCount, $companions, $guestIdentifier) {
            $guest = $this->guestResolveService->execute($event, $guestData, $guestIdentifier);

            $this->detachFromHost($guest);

            if (! $attending) {
                $this->releaseCompanions($guest);
            } elseif ($event->collectsRsvpCompanions()) {
                // The headcount is derived from who was actually linked, so
                // companions already counted elsewhere aren't counted twice.
                $guestsCount = 1 + $this->syncCompanions($event, $guest, $companions);
            }

            $guest->forceFill([
                'rsvp_status' => $attending ? Guest::RSVP_CONFIRMED : Guest::RSVP_DECLINED,
                'rsvp_guests_count' => $attending ? $guestsCount : null,
                'rsvp_responded_at' => now(),
            ])->save();

            return $guest;
        });
    }

    /**
     * Someone listed as a companion who now answers the RSVP themselves
     * becomes an individual confirmation, and stops counting towards the
     * headcount of the guest who listed them.
     */
    private function detachFromHost(Guest $guest): void
    {
        if (! $guest->companion_of_guest_id) {
            return;
        }

        $host = $guest->companionOf;

        $guest->companion_of_guest_id = null;

        if ($host && $host->rsvp_status === Guest::RSVP_CONFIRMED && $host->rsvp_guests_count > 1) {
            $host->decrement('rsvp_guests_count');
        }
    }

    /**
     * Links each submitted companion to the host guest, reusing an existing
     * guest of the event when the contact details match (so someone who
     * already bought a gift isn't duplicated). A person who already answered
     * the RSVP on their own — or was listed by another guest — keeps that
     * record and isn't counted again here.
     *
     * @param  array<int, array{name: string, whatsapp?: ?string, email?: ?string, cpf?: ?string}>  $companions
     * @return int How many companions ended up linked to the host
     */
    private function syncCompanions(Event $event, Guest $host, array $companions): int
    {
        $linkedIds = [];

        foreach ($companions as $data) {
            $match = $this->guestResolveService->findByContact($event, $data, except: $host);

            if ($match && in_array($match->id, $linkedIds, true)) {
                continue;
            }

            $alreadyCounted = $match
                && $match->companion_of_guest_id !== $host->id
                && ($match->companion_of_guest_id !== null || $match->rsvp_status !== null);

            if ($alreadyCounted) {
                continue;
            }

            $companion = $match ?? new Guest(['event_id' => $event->id]);

            $companion->fill(array_filter($data, fn ($value) => filled($value)));
            $companion->forceFill([
                'companion_of_guest_id' => $host->id,
                'rsvp_status' => Guest::RSVP_CONFIRMED,
                'rsvp_guests_count' => null,
                'rsvp_responded_at' => now(),
            ])->save();

            $linkedIds[] = $companion->id;
        }

        $this->releaseCompanions($host, except: $linkedIds);

        return count($linkedIds);
    }

    /**
     * Companions no longer on the host's list are removed — unless they have
     * gift orders of their own, in which case they're kept as a regular
     * guest without an RSVP.
     *
     * @param  array<int, int>  $except
     */
    private function releaseCompanions(Guest $host, array $except = []): void
    {
        $host->companions()
            ->whereKeyNot($except)
            ->withCount('orders')
            ->get()
            ->each(function (Guest $companion): void {
                // Taking someone off one's own companion list is the guest
                // editing their answer, not a removal to audit or undo.
                if ($companion->orders_count === 0) {
                    $companion->disableLogging()->forceDelete();

                    return;
                }

                $companion->forceFill([
                    'companion_of_guest_id' => null,
                    'rsvp_status' => null,
                    'rsvp_guests_count' => null,
                    'rsvp_responded_at' => null,
                ])->save();
            });
    }
}
