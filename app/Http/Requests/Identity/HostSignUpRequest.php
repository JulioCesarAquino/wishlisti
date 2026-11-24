<?php

namespace App\Http\Requests\Identity;

use App\Models\User;
use App\Support\Identity\Attribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class HostSignUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'whatsapp' => ['required', 'string', 'min:10', 'max:30'],
            'password' => ['required', 'confirmed', Password::defaults()],
            // Hidden from people; bots fill it in.
            'website' => ['prohibited'],
            ...Attribution::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Já existe uma conta com este e-mail. Entre com ele ou use "Esqueceu a senha?".',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'email' => 'e-mail',
            'whatsapp' => 'WhatsApp',
            'password' => 'senha',
        ];
    }
}
