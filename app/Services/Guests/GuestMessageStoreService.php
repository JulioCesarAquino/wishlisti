<?php

namespace App\Services\Guests;

use App\Enums\Premium\Feature;
use App\Models\Events\Event;
use App\Models\Guests\GuestMessage;
use Illuminate\Validation\ValidationException;

class GuestMessageStoreService
{
    /**
     * A message on the guestbook (premium). It's kept unapproved — hidden
     * from the page — until the host approves it, so nothing shows up
     * publicly without their say.
     */
    public function execute(Event $event, string $authorName, string $message, ?string $guestIdentifier): GuestMessage
    {
        if (! $event->hasFeature(Feature::Guestbook)) {
            throw ValidationException::withMessages([
                'message' => 'Este evento não tem mural de recados.',
            ]);
        }

        $guest = $guestIdentifier
            ? $event->guests()->where('identifier', $guestIdentifier)->first()
            : null;

        return $event->messages()->create([
            'guest_id' => $guest?->id,
            'author_name' => $authorName,
            'message' => $message,
        ]);
    }
}
