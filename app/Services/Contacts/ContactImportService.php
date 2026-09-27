<?php

namespace App\Services\Contacts;

use App\Models\Contacts\Contact;
use App\Models\Events\Event;
use App\Support\ContactMatcher;

class ContactImportService
{
    /**
     * Copies the event's guests into its host's address book. Someone the
     * host already has (same CPF, WhatsApp or e-mail) isn't duplicated —
     * the contact only gets the details it was missing, since the host may
     * have curated it by hand.
     *
     * @return int How many new contacts were created
     */
    public function execute(Event $event): int
    {
        $host = $event->user;
        $contacts = $host->contacts()->get();
        $created = 0;

        foreach ($event->guests()->orderBy('id')->get() as $guest) {
            $details = $guest->contactDetails();

            /** @var Contact|null $contact */
            $contact = ContactMatcher::firstMatch($contacts, $details);

            if ($contact) {
                $contact->update(array_filter(
                    $details,
                    fn (?string $value, string $field) => filled($value) && blank($contact->{$field}),
                    ARRAY_FILTER_USE_BOTH,
                ));

                continue;
            }

            $contacts->push($host->contacts()->create($details));
            $created++;
        }

        return $created;
    }
}
