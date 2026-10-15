<?php

namespace App\Services\Guests;

use App\Models\Guests\Guest;
use App\Models\Guests\GuestMessage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GuestMergeService
{
    /** Details a record lacking them takes from the one being merged in. */
    private const MERGED_FIELDS = ['whatsapp', 'email', 'cpf', 'age'];

    /**
     * Joins two records of the same person into one: someone listed as a
     * companion by a relative who also confirmed on their own with other
     * contact details, so nothing told the two apart and they're counted
     * twice.
     *
     * The record that stays is the one whose RSVP prevails (see rank()).
     * It takes the other's WhatsApp, e-mail, CPF and age where it has none,
     * its gifts, guestbook messages and companions. Whoever had listed the
     * other record as a companion counts one person fewer.
     *
     * @return Guest The record that stays
     */
    public function execute(Guest $first, Guest $second): Guest
    {
        if ($first->is($second) || $first->event_id !== $second->event_id) {
            throw new InvalidArgumentException('Only two different guests of the same event can be merged.');
        }

        if ($first->companion_of_guest_id === $second->id || $second->companion_of_guest_id === $first->id) {
            throw new InvalidArgumentException('A guest and their own companion are different people.');
        }

        [$kept, $merged] = $this->rank($first, $second) >= $this->rank($second, $first)
            ? [$first, $second]
            : [$second, $first];

        return DB::transaction(function () use ($kept, $merged) {
            foreach (self::MERGED_FIELDS as $field) {
                if (blank($kept->{$field}) && filled($merged->{$field})) {
                    $kept->setAttribute($field, $merged->getAttribute($field));
                }
            }

            $kept->save();

            $merged->orders()->update(['guest_id' => $kept->id]);
            GuestMessage::where('guest_id', $merged->id)->update(['guest_id' => $kept->id]);

            $this->moveCompanions($merged, $kept);

            $merged->companionOf?->adjustHeadcount(-1);

            // Not a removal to audit or undo: the person is still on the
            // list, now in a single record.
            $merged->disableLogging()->forceDelete();

            activity('guest')
                ->performedOn($kept)
                ->event('merged')
                ->log('unificou dois registros do mesmo convidado');

            return $kept->refresh();
        });
    }

    /**
     * How strongly a record's RSVP holds against the other's: an answer
     * the person gave themselves beats being listed by someone else, which
     * beats no answer at all. Between equals, the latest answer — the
     * person's last word — and then the oldest record.
     *
     * @return array{int, int, int}
     */
    private function rank(Guest $guest, Guest $other): array
    {
        $standing = match (true) {
            $guest->rsvp_status !== null && $guest->companion_of_guest_id === null => 2,
            $guest->companion_of_guest_id !== null => 1,
            default => 0,
        };

        return [
            $standing,
            $guest->rsvp_responded_at?->getTimestamp() ?? 0,
            $guest->id < $other->id ? 1 : 0,
        ];
    }

    /**
     * The companions of the record going away move to the one that stays,
     * and count in its headcount. If the person isn't coming after all,
     * they're kept as guests without an answer instead.
     */
    private function moveCompanions(Guest $from, Guest $to): void
    {
        $companions = $from->companions()->get();

        if ($companions->isEmpty()) {
            return;
        }

        $joins = $to->rsvp_status === Guest::RSVP_CONFIRMED && $to->rsvp_guests_count !== null;

        $companions->each(fn (Guest $companion) => $companion->forceFill($joins
            ? ['companion_of_guest_id' => $to->id]
            : ['companion_of_guest_id' => null, 'rsvp_status' => null, 'rsvp_guests_count' => null, 'rsvp_responded_at' => null],
        )->save());

        if ($joins) {
            $to->adjustHeadcount($companions->count());
        }
    }
}
