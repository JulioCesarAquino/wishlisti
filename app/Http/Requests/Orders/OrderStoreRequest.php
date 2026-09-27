<?php

namespace App\Http\Requests\Orders;

use App\Http\Requests\Guests\Concerns\ValidatesGuestContact;
use App\Models\Events\Event;
use App\Models\Orders\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderStoreRequest extends FormRequest
{
    use ValidatesGuestContact;

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
            ...$this->guestContactRules('guest', $event->rsvpRequiredFields()),
            'fulfillment' => ['sometimes', Rule::in([Order::FULFILLMENT_ONLINE, Order::FULFILLMENT_IN_PERSON])],
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
            'guest.cpf' => 'CPF',
            'fulfillment' => 'forma de presentear',
            'message' => 'mensagem',
            'items' => 'itens',
        ];
    }
}
