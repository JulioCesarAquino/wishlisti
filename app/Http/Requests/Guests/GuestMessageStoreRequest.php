<?php

namespace App\Http\Requests\Guests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuestMessageStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Anonymous: the name is optional (only the admin ever sees it).
            'author_name' => [Rule::requiredIf(! $this->boolean('anonymous')), 'nullable', 'string', 'max:100'],
            'anonymous' => ['sometimes', 'boolean'],
            'message' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'author_name' => 'nome',
            'message' => 'recado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'author_name.required' => 'Por favor, informe seu nome, ou marque "Enviar anonimamente".',
            'message.required' => 'Escreva o seu recado.',
        ];
    }
}
