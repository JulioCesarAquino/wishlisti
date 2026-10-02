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

        // An anonymous giver may still say who they are — only the admin
        // gets to see it — but nothing is mandatory.
        $anonymous = $this->boolean('anonymous');

        return [
            ...$this->guestContactRules('guest', $anonymous ? [] : $event->rsvpRequiredFields(), nameRequired: ! $anonymous),
            'fulfillment' => ['sometimes', Rule::in([Order::FULFILLMENT_ONLINE, Order::FULFILLMENT_IN_PERSON])],
            'message' => ['nullable', 'string', 'max:1000'],
            // Whoever hands the gift over is seen doing it: anonymity only
            // makes sense for gifts paid online.
            'anonymous' => ['sometimes', 'boolean', 'declined_if:fulfillment,'.Order::FULFILLMENT_IN_PERSON],
            // Either items from the list, or a contribution of any amount.
            'free_amount' => ['nullable', 'numeric', 'min:1', 'max:100000', 'prohibits:items'],
            'items' => ['required_without:free_amount', 'array', 'min:1'],
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
            'free_amount' => 'valor',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->guestContactMessages('guest'),
            'anonymous.declined_if' => 'Presentes entregues pessoalmente não podem ser anônimos. Desmarque "Presentear anonimamente" ou pague online.',
        ];
    }
}
