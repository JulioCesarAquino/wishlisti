<?php

namespace App\Services\Guests;

use App\Models\Guests\Guest;
use Illuminate\Support\Str;

class GuestAnonymizeService
{
    /**
     * Erases the guest's personal data while keeping the guest row — and
     * so their orders and RSVP — in the event's history. This is what a
     * data deletion request (LGPD) turns into for a guest who gave gifts,
     * since the financial record itself has to be kept.
     *
     * The identifier is replaced too, so the browser cookie that pointed at
     * this guest no longer resolves to them.
     */
    public function execute(Guest $guest): void
    {
        $guest->forceFill([
            'name' => Guest::ANONYMIZED_NAME,
            'whatsapp' => null,
            'email' => null,
            'cpf' => null,
            'identifier' => (string) Str::uuid(),
        ])->save();

        activity('guest')
            ->performedOn($guest)
            ->event('anonymized')
            ->log('anonimizou os dados do convidado');
    }
}
