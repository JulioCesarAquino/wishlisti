<?php

namespace App\Services\Guests;

use App\Models\Events\Event;
use App\Models\Guests\Guest;
use Illuminate\Support\Facades\DB;

/**
 * The host's own changes to the guest list: someone added by hand (a
 * pre-list of who's invited, still without an answer), a typo fixed, or a
 * presence confirmed over the phone.
 */
class GuestManageService
{
    /**
     * @param  array<string, mixed>  $data  name, whatsapp, email, cpf, age, rsvp_status, rsvp_guests_count, rsvp_children_count
     */
    public function save(Event $event, array $data, ?Guest $guest = null): Guest
    {
        return DB::transaction(function () use ($event, $data, $guest) {
            $guest ??= new Guest(['event_id' => $event->id]);

            $guest->fill([
                'name' => $data['name'],
                'whatsapp' => $data['whatsapp'] ?? null,
                'email' => $data['email'] ?? null,
                'cpf' => $data['cpf'] ?? null,
                'age' => $data['age'] ?? null,
            ]);

            // A companion's presence follows whoever listed them.
            if ($guest->companion_of_guest_id !== null) {
                $guest->save();

                return $guest;
            }

            $status = $data['rsvp_status'] ?? null;
            $confirmed = $status === Guest::RSVP_CONFIRMED;
            $listed = $guest->exists ? $guest->companions()->count() : 0;
            $changed = ! $guest->exists || $guest->rsvp_status !== $status;

            $guest->forceFill([
                'rsvp_status' => $status,
                // Never fewer people than the companions already listed.
                'rsvp_guests_count' => $confirmed ? max((int) ($data['rsvp_guests_count'] ?? 1), 1 + $listed) : null,
                'rsvp_children_count' => $confirmed && $event->asksRsvpChildrenCount() ? ($data['rsvp_children_count'] ?? null) : null,
                'rsvp_responded_at' => $status === null ? null : ($changed ? now() : $guest->rsvp_responded_at),
            ])->save();

            $this->syncCompanionsStatus($guest);

            return $guest;
        });
    }

    /**
     * Marks the guest as coming, with the party they had already told about
     * (or just themselves).
     */
    public function confirm(Guest $guest): void
    {
        if ($guest->companion_of_guest_id !== null || $guest->rsvp_status === Guest::RSVP_CONFIRMED) {
            return;
        }

        DB::transaction(function () use ($guest) {
            $guest->forceFill([
                'rsvp_status' => Guest::RSVP_CONFIRMED,
                'rsvp_guests_count' => 1 + $guest->companions()->count(),
                'rsvp_responded_at' => now(),
            ])->save();

            $this->syncCompanionsStatus($guest);
        });
    }

    private function syncCompanionsStatus(Guest $guest): void
    {
        $guest->companions()->update(['rsvp_status' => $guest->rsvp_status]);
    }
}
