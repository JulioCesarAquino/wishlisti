<?php

namespace App\Services\Guests;

use App\Models\Events\Event;
use App\Models\Guests\Guest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RsvpStoreService
{
    public function __construct(
        protected GuestResolveService $guestResolveService,
    ) {}

    /**
     * @param  array{name: string, whatsapp?: ?string, email?: ?string, cpf?: ?string, age?: ?int}  $guestData
     * @param  array<int, array{name: string, whatsapp?: ?string, email?: ?string, cpf?: ?string, age?: ?int}>  $companions
     * @param  Guest|null  $invited  whose personal invitation link the browser opened
     *
     * @throws ValidationException beyond the guest's companion limit or the event's places left, or without their invitation link when the host only accepts it
     */
    public function execute(
        Event $event,
        array $guestData,
        bool $attending,
        ?int $guestsCount,
        array $companions,
        ?string $guestIdentifier,
        ?int $childrenCount = null,
        ?Guest $invited = null,
    ): Guest {
        return DB::transaction(function () use ($event, $guestData, $attending, $guestsCount, $companions, $guestIdentifier, $childrenCount, $invited) {
            // One answer at a time per event, so two people can't both take
            // the last place.
            $placesTakenBefore = null;

            if ($event->rsvpCapacity() !== null) {
                Event::query()->whereKey($event->getKey())->lockForUpdate()->first();
                $placesTakenBefore = $event->rsvpTakenPlaces();
            }

            $guest = $this->guestResolveService->execute($event, $guestData, $guestIdentifier);

            $this->ensureInvited($event, $guest, $invited);

            if ($attending) {
                $this->ensureWithinCompanionLimit($event, $guest, $guestsCount, $companions);
            }

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
                'rsvp_children_count' => $attending && $event->asksRsvpChildrenCount() ? $childrenCount : null,
                'rsvp_responded_at' => now(),
            ])->save();

            if ($placesTakenBefore !== null) {
                $this->ensureWithinCapacity($event, $guest, $placesTakenBefore);
            }

            return $guest;
        });
    }

    /**
     * Counted once the answer is saved — what it changed, companions who
     * moved and children who don't take a place included — and thrown
     * back (rolling it all back) only when it takes more places beyond the
     * capacity: someone bringing fewer people, or the same, is never
     * turned away, even after the host lowered the capacity.
     */
    private function ensureWithinCapacity(Event $event, Guest $guest, int $placesTakenBefore): void
    {
        $capacity = (int) $event->rsvpCapacity();
        $placesTaken = $event->rsvpTakenPlaces();

        if ($placesTaken <= $capacity || $placesTaken <= $placesTakenBefore) {
            return;
        }

        $placesLeft = max(0, $capacity - ($placesTaken - $event->rsvpTakenPlaces($guest)));
        $children = $event->capacityExcludesChildren() ? " (crianças com menos de {$event->childAgeLimit()} anos não contam)" : '';

        throw ValidationException::withMessages([
            'guests_count' => match ($placesLeft) {
                0 => 'As vagas do evento acabaram. Fale com os anfitriões.',
                1 => "Resta só 1 vaga no evento{$children}.",
                default => "Restam só {$placesLeft} vagas no evento{$children}.",
            },
        ]);
    }

    /**
     * When the host only accepts confirmations by the personal link, the
     * one answering must be whoever that link belongs to. Thrown inside the
     * transaction, so nothing the resolution saved stays.
     */
    private function ensureInvited(Event $event, Guest $guest, ?Guest $invited): void
    {
        if (! $event->rsvpIsInviteOnly() || $guest->is($invited)) {
            return;
        }

        throw ValidationException::withMessages([
            'guest.name' => $invited
                ? "Este convite é de {$invited->name}. Para confirmar outra pessoa, use o link do convite dela."
                : 'A confirmação de presença é feita pelo link do seu convite. Não recebeu? Fale com os anfitriões.',
        ]);
    }

    /**
     * @param  array<int, mixed>  $companions
     */
    private function ensureWithinCompanionLimit(Event $event, Guest $guest, ?int $guestsCount, array $companions): void
    {
        $asked = $event->collectsRsvpCompanions() ? count($companions) : max(0, (int) $guestsCount - 1);
        $allowed = $guest->setRelation('event', $event)->allowedCompanions();

        if ($asked <= $allowed) {
            return;
        }

        throw ValidationException::withMessages([
            'guests_count' => $allowed === 0
                ? 'Seu convite é individual: confirme só a sua presença.'
                : 'Seu convite é para até '.($allowed + 1).' pessoas, contando com você.',
        ]);
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
     * Companions no longer on the guest's list leave it — to the trash, or
     * kept as guests without an answer if they gave a gift — never deleted
     * for good: a family sharing a phone can replace someone else's list.
     *
     * @param  array<int, int>  $except
     */
    private function releaseCompanions(Guest $host, array $except = []): void
    {
        $host->companions()
            ->whereKeyNot($except)
            ->get()
            ->each->leaveCompanionList('saiu da lista de acompanhantes quando o convidado refez a confirmação');
    }
}
