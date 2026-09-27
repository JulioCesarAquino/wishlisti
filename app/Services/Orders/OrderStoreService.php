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
     * @param  array{name: string, whatsapp: string, email: ?string}  $guestData
     * @param  array<int, array{event_product_id: int, quantity: int}>  $items
     */
    public function execute(
        Event $event,
        array $guestData,
        array $items,
        ?string $message,
        ?string $guestIdentifier,
        bool $anonymous = false,
    ): Order {
        if (! $event->acceptsOnlineGifts()) {
            throw ValidationException::withMessages([
                'items' => 'Este evento não recebe presentes online.',
            ]);
        }

        return DB::transaction(function () use ($event, $guestData, $items, $message, $guestIdentifier, $anonymous) {
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
                'status' => Order::STATUS_PENDING,
                'total_amount' => $totalAmount,
                'message' => $message,
                'is_anonymous' => $anonymous,
            ]);

            $order->items()->createMany($orderItems);

            return $order->setRelation('guest', $guest);
        });
    }
}
