<?php

namespace App\Http\Requests\Guests\Concerns;

use App\Rules\Guests\Cpf;

/**
 * Name plus the contact fields the event makes mandatory (see
 * Event::rsvpRequiredFields()) — the same for the RSVP and for gifts, so
 * a guest is identified the same way everywhere.
 */
trait ValidatesGuestContact
{
    /**
     * @param  array<int, string>  $requiredFields
     * @return array<string, mixed>
     */
    protected function guestContactRules(string $prefix, array $requiredFields): array
    {
        $presence = fn (string $field) => in_array($field, $requiredFields, true) ? 'required' : 'nullable';

        return [
            "{$prefix}.name" => ['required', 'string', 'max:255'],
            "{$prefix}.whatsapp" => [$presence('whatsapp'), 'string', 'max:30'],
            "{$prefix}.email" => [$presence('email'), 'email', 'max:255'],
            "{$prefix}.cpf" => [$presence('cpf'), 'string', new Cpf],
        ];
    }
}
