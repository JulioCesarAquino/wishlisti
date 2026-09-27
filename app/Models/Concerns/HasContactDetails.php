<?php

namespace App\Models\Concerns;

use App\Support\ContactMatcher;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * For models holding a person's name, WhatsApp, e-mail and CPF.
 */
trait HasContactDetails
{
    /**
     * Stored as digits only, so it can be matched regardless of how it was
     * typed ("123.456.789-09" vs "12345678909").
     *
     * @return Attribute<string|null, string|null>
     */
    protected function cpf(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => ContactMatcher::digits($value),
        );
    }

    /**
     * @return array{name: string, whatsapp: ?string, email: ?string, cpf: ?string}
     */
    public function contactDetails(): array
    {
        return [
            'name' => $this->name,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'cpf' => $this->cpf,
        ];
    }
}
