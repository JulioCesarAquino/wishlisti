<?php

namespace App\Services\Guests;

use App\Models\Contacts\Contact;
use App\Models\Events\Event;
use App\Support\ContactMatcher;

class GuestImportService
{
    /**
     * Pre-registers contacts from the host's address book as guests of the
     * event, still without an RSVP. When one of them later confirms, the
     * RSVP lands on this same guest (it's matched by contact details), so
     * the host can see who on their list hasn't answered yet.
     *
     * Contacts from the address book of someone who doesn't run the event
     * (its owner or a co-host) are ignored, as are people already on the
     * event's guest list.
     *
     * @param  iterable<Contact>  $contacts
     * @return int How many guests were added
     */
    public function execute(Event $event, iterable $contacts): int
    {
        $guests = $event->guests()->get();
        $created = 0;

        foreach ($contacts as $contact) {
            if ($contact->user_id !== $event->user_id && ! $event->coHosts->contains('id', $contact->user_id)) {
                continue;
            }

            if (ContactMatcher::firstMatch($guests, $contact->contactDetails())) {
                continue;
            }

            $guests->push($event->guests()->create($contact->contactDetails()));
            $created++;
        }

        return $created;
    }
}
