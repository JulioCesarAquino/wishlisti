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
    protected function guestContactRules(string $prefix, array $requiredFields, bool $nameRequired = true): array
    {
        $presence = fn (string $field) => in_array($field, $requiredFields, true) ? 'required' : 'nullable';

        return [
            "{$prefix}.name" => [$nameRequired ? 'required' : 'nullable', 'string', 'max:255'],
            "{$prefix}.whatsapp" => [$presence('whatsapp'), 'string', 'max:30'],
            "{$prefix}.email" => [$presence('email'), 'email', 'max:255'],
            "{$prefix}.cpf" => [$presence('cpf'), 'string', new Cpf],
        ];
    }

    /**
     * Guests aren't filling in a system form: a polite word per field, in
     * place of the generic "É obrigatória a indicação de um valor…".
     *
     * @param  string  $whose  "seu" for the guest, "do acompanhante"…
     * @return array<string, string>
     */
    protected function guestContactMessages(string $prefix, string $whose = 'seu'): array
    {
        $own = $whose === 'seu';
        $field = fn (string $article, string $name) => $own ? "{$article} {$name}" : "{$article} {$name} {$whose}";

        return [
            "{$prefix}.name.required" => 'Por favor, informe '.$field($own ? 'seu' : 'o', 'nome').'.',
            "{$prefix}.whatsapp.required" => 'Por favor, informe '.$field($own ? 'seu' : 'o', 'WhatsApp').'.',
            "{$prefix}.email.required" => 'Por favor, informe '.$field($own ? 'seu' : 'o', 'e-mail').'.',
            "{$prefix}.email.email" => 'Esse e-mail parece incompleto. Pode conferir?',
            "{$prefix}.cpf.required" => 'Por favor, informe '.$field($own ? 'seu' : 'o', 'CPF').'.',
            "{$prefix}.*.max" => 'Esse texto ficou longo demais. Pode encurtar?',
        ];
    }
}
