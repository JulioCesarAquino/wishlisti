<?php

namespace App\Services\Guests;

use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Support\ContactMatcher;

class GuestResolveService
{
    /**
     * Find the guest for this event, updating their contact details, or
     * create a new one if none matches.
     *
     * Resolution first tries the browser cookie identifier. That fails
     * whenever the same person visits from a different browser/device, so
     * we fall back to matching an existing guest of this same event by any
     * contact detail they gave (see findByContact()) instead of fragmenting
     * their gift and RSVP history across duplicate guest rows.
     *
     * Without a name (an anonymous gift from someone who didn't fill it in),
     * a new guest is saved as Guest::ANONYMOUS_GIVER_NAME — an existing one
     * keeps theirs.
     *
     * @param  array{name?: ?string, whatsapp?: ?string, email?: ?string, cpf?: ?string}  $guestData
     */
    public function execute(Event $event, array $guestData, ?string $guestIdentifier): Guest
    {
        $guest = $guestIdentifier
            ? $event->guests()->where('identifier', $guestIdentifier)->first()
            : null;

        $guest ??= $this->findByContact($event, $guestData);

        if ($guest) {
            // Blank fields are left out so a form that doesn't ask for, say,
            // the CPF doesn't wipe the one we already know.
            $guest->update(array_filter($guestData, fn ($value) => filled($value)));

            return $guest;
        }

        return $event->guests()->create([
            ...$guestData,
            'name' => filled($guestData['name'] ?? null) ? $guestData['name'] : Guest::ANONYMOUS_GIVER_NAME,
        ]);
    }

    /**
     * An existing guest of this event with the same contact details (see
     * ContactMatcher).
     *
     * Compared in PHP (not a DB-side regex) so this stays portable across
     * the MySQL connection used in production and the SQLite one used in
     * tests, and because per-event guest lists are small enough that
     * loading them isn't a real cost.
     *
     * @param  array{whatsapp?: ?string, email?: ?string, cpf?: ?string}  $contact
     */
    public function findByContact(Event $event, array $contact, ?Guest $except = null): ?Guest
    {
        $guests = $event->guests()
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->get();

        return ContactMatcher::firstMatch($guests, $contact);
    }
}
