<?php

namespace App\Http\Requests\Guests;

use App\Http\Requests\Guests\Concerns\ValidatesGuestContact;
use App\Models\Events\Event;
use Illuminate\Foundation\Http\FormRequest;

class RsvpStoreRequest extends FormRequest
{
    use ValidatesGuestContact;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Which contact fields are mandatory — and whether each companion's
     * details are asked for — comes from the event's RSVP settings.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Event $event */
        $event = $this->route('event');

        $rules = [
            ...$this->guestContactRules('guest', $event->rsvpRequiredFields()),
            'attending' => ['required', 'boolean'],
            'guests_count' => ['required_if:attending,true', 'nullable', 'integer', 'min:1', 'max:20'],
        ];

        if ($event->collectsRsvpCompanions() && $this->boolean('attending')) {
            $companionsCount = max(0, (int) $this->input('guests_count', 1) - 1);

            $rules['companions'] = [$companionsCount > 0 ? 'required' : 'nullable', 'array', "size:{$companionsCount}"];
            $rules += $this->guestContactRules('companions.*', $event->rsvpRequiredFields());
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'guest.name' => 'nome',
            'guest.whatsapp' => 'WhatsApp',
            'guest.email' => 'e-mail',
            'guest.cpf' => 'CPF',
            'attending' => 'confirmação',
            'guests_count' => 'quantidade de pessoas',
            'companions' => 'acompanhantes',
            'companions.*.name' => 'nome do acompanhante',
            'companions.*.whatsapp' => 'WhatsApp do acompanhante',
            'companions.*.email' => 'e-mail do acompanhante',
            'companions.*.cpf' => 'CPF do acompanhante',
        ];
    }
}
