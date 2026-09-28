<?php

namespace App\Services\Orders;

use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\Orders\Order;
use App\Services\Guests\GuestResolveService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStoreService
{
    public function __construct(
        protected GuestResolveService $guestResolveService,
    ) {}

    /**
     * Online gifts start pending until Mercado Pago confirms the payment
     * (only then is the stock taken). In-person gifts are reservations:
     * the stock is taken right away, so no one else picks the same item.
     *
     * @param  array{name: string, whatsapp?: ?string, email?: ?string, cpf?: ?string}  $guestData
     * @param  array<int, array{event_product_id: int, quantity: int}>  $items
     */
    public function execute(
        Event $event,
        array $guestData,
        array $items,
        ?string $message,
        ?string $guestIdentifier,
        bool $anonymous = false,
        string $fulfillment = Order::FULFILLMENT_ONLINE,
        ?float $freeAmount = null,
    ): Order {
        if ($freeAmount !== null) {
            return $this->storeFreeAmount($event, $guestData, $freeAmount, $message, $guestIdentifier, $anonymous);
        }

        if (! $event->showsGiftItems()) {
            throw ValidationException::withMessages([
                'items' => 'Este evento não tem lista de presentes.',
            ]);
        }

        $inPerson = $fulfillment === Order::FULFILLMENT_IN_PERSON;

        if ($inPerson ? ! $event->acceptsInPersonGifts() : ! $event->acceptsOnlineGifts()) {
            throw ValidationException::withMessages([
                'items' => $inPerson
                    ? 'Este evento não recebe presentes para entrega pessoal.'
                    : 'Este evento não recebe presentes online.',
            ]);
        }

        return DB::transaction(function () use ($event, $guestData, $items, $message, $guestIdentifier, $anonymous, $inPerson, $fulfillment) {
            $guest = $this->guestResolveService->execute($event, $guestData, $guestIdentifier);

            $orderItems = [];
            $totalAmount = 0;

            foreach ($items as $item) {
                /** @var EventProduct $product */
                $product = EventProduct::where('event_id', $event->id)
                    ->where('id', $item['event_product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($product->quantityAvailable() < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => "Quantidade indisponível para \"{$product->name}\".",
                    ]);
                }

                if ($inPerson) {
                    $product->increment('quantity_purchased', $item['quantity']);
                }

                $subtotal = $product->price * $item['quantity'];
                $totalAmount += $subtotal;

                $orderItems[] = [
                    'event_product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                ];
            }

            $order = Order::create([
                'event_id' => $event->id,
                'guest_id' => $guest->id,
                'status' => $inPerson ? Order::STATUS_RESERVED : Order::STATUS_PENDING,
                'fulfillment' => $fulfillment,
                'total_amount' => $totalAmount,
                'message' => $message,
                'is_anonymous' => $anonymous,
            ]);

            $order->items()->createMany($orderItems);

            return $order->setRelation('guest', $guest);
        });
    }

    /**
     * A contribution of any amount, without picking an item: always paid
     * online, and there's no stock to take.
     *
     * @param  array{name: string, whatsapp?: ?string, email?: ?string, cpf?: ?string}  $guestData
     */
    private function storeFreeAmount(
        Event $event,
        array $guestData,
        float $amount,
        ?string $message,
        ?string $guestIdentifier,
        bool $anonymous,
    ): Order {
        if (! $event->acceptsFreeAmount()) {
            throw ValidationException::withMessages([
                'free_amount' => 'Este evento não recebe contribuições de valor livre.',
            ]);
        }

        return DB::transaction(function () use ($event, $guestData, $amount, $message, $guestIdentifier, $anonymous) {
            $guest = $this->guestResolveService->execute($event, $guestData, $guestIdentifier);

            $order = Order::create([
                'event_id' => $event->id,
                'guest_id' => $guest->id,
                'status' => Order::STATUS_PENDING,
                'fulfillment' => Order::FULFILLMENT_ONLINE,
                'is_free_amount' => true,
                'total_amount' => round($amount, 2),
                'message' => $message,
                'is_anonymous' => $anonymous,
            ]);

            return $order->setRelation('guest', $guest);
        });
    }
}
