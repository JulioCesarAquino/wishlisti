<?php

namespace App\Http\Requests\Guests\Concerns;

use App\Models\Events\EventRsvpSetting;
use App\Rules\Guests\Cpf;

/**
 * Name plus the fields the event asks for (see Event::rsvpFields()) — the
 * same for the RSVP and for gifts, so a guest is identified the same way
 * everywhere. A field the event doesn't ask for is dropped, even if sent.
 */
trait ValidatesGuestContact
{
    /**
     * @param  array<string, string>  $fields  field => EventRsvpSetting::FIELD_*
     * @return array<string, mixed>
     */
    protected function guestContactRules(string $prefix, array $fields, bool $nameRequired = true): array
    {
        $rules = [
            "{$prefix}.name" => [$nameRequired ? 'required' : 'nullable', 'string', 'max:255'],
        ];

        $formats = [
            'whatsapp' => ['string', 'max:30'],
            'email' => ['email', 'max:255'],
            'cpf' => ['string', new Cpf],
            'age' => ['integer', 'min:0', 'max:120'],
        ];

        foreach ($fields as $field => $mode) {
            $rules["{$prefix}.{$field}"] = match ($mode) {
                EventRsvpSetting::FIELD_REQUIRED => ['required', ...$formats[$field]],
                EventRsvpSetting::FIELD_OPTIONAL => ['nullable', ...$formats[$field]],
                default => ['exclude'],
            };
        }

        return $rules;
    }

    /**
     * The same fields, none of them required (an anonymous gift).
     *
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    protected function optionalFields(array $fields): array
    {
        return array_map(fn (string $mode) => $mode === EventRsvpSetting::FIELD_REQUIRED ? EventRsvpSetting::FIELD_OPTIONAL : $mode, $fields);
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
            "{$prefix}.age.required" => 'Por favor, informe '.$field($own ? 'sua' : 'a', 'idade').'.',
            "{$prefix}.age.integer" => 'A idade deve ser um número inteiro, em anos.',
            "{$prefix}.age.min" => 'A idade deve ser um número inteiro, em anos.',
            "{$prefix}.age.max" => 'Essa idade parece errada. Pode conferir?',
            "{$prefix}.*.max" => 'Esse texto ficou longo demais. Pode encurtar?',
        ];
    }
}
