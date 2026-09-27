<?php

namespace App\Http\Requests\Orders;

use App\Models\Events\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderStoreRequest extends FormRequest
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
        /** @var Event $event */
        $event = $this->route('event');

        return [
            'guest.name' => ['required', 'string', 'max:255'],
            'guest.whatsapp' => ['required', 'string', 'max:30'],
            'guest.email' => ['nullable', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
            'anonymous' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.event_product_id' => [
                'required',
                'integer',
                Rule::exists('event_products', 'id')
                    ->where('event_id', $event->id)
                    ->where('is_active', true),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
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
            'message' => 'mensagem',
            'items' => 'itens',
        ];
    }
}
