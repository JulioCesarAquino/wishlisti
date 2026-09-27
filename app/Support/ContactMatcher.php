<?php

namespace App\Support;

use App\Models\Contacts\Contact;
use App\Models\Guests\Guest;

/**
 * Recognises the same person across records by their contact details:
 * CPF, WhatsApp (digits only, ignoring formatting) or e-mail
 * (case-insensitive) — tried in that order, from the most to the least
 * unique identifier.
 */
class ContactMatcher
{
    public static function digits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    public static function email(?string $value): ?string
    {
        $email = mb_strtolower(trim((string) $value));

        return $email === '' ? null : $email;
    }

    /**
     * @template T of Guest|Contact
     *
     * @param  iterable<T>  $candidates
     * @param  array{whatsapp?: ?string, email?: ?string, cpf?: ?string}  $contact
     * @return T|null
     */
    public static function firstMatch(iterable $candidates, array $contact): Guest|Contact|null
    {
        $candidates = collect($candidates);

        $cpf = self::digits($contact['cpf'] ?? null);
        $whatsapp = self::digits($contact['whatsapp'] ?? null);
        $email = self::email($contact['email'] ?? null);

        return ($cpf ? $candidates->first(fn ($candidate) => $candidate->cpf === $cpf) : null)
            ?? ($whatsapp ? $candidates->first(fn ($candidate) => self::digits($candidate->whatsapp) === $whatsapp) : null)
            ?? ($email ? $candidates->first(fn ($candidate) => self::email($candidate->email) === $email) : null);
    }
}
