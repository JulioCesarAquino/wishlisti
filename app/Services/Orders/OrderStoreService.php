<?php

namespace App\Services\Orders;

use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\Orders\Order;
use App\Services\Guests\GuestResolveService;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStoreService
{
    public function __construct(
        protected GuestResolveService $guestResolveService,
        protected OrderPaymentUpdateService $paymentUpdateService,
    ) {}

    /**
     * Online gifts start pending until Mercado Pago confirms the payment
     * (only then is the stock taken). In-person gifts are reservations:
     * the stock is taken right away, so no one else picks the same item.
     *
     * @param  array{name?: ?string, whatsapp?: ?string, email?: ?string, cpf?: ?string}  $guestData
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

        $resumed = $inPerson ? null : $this->resumeOpenOrder($event, $guestIdentifier, fn (Order $order) => ! $order->is_free_amount
            && $this->itemsKey($order->items->map(fn ($item) => ['event_product_id' => $item->event_product_id, 'quantity' => $item->quantity])->all()) === $this->itemsKey($items));

        // Its payment already started (a Pix to pay) or just went through:
        // shown as it is, never charged again.
        if ($resumed && ($resumed->status === Order::STATUS_PAID || filled($resumed->payment_id))) {
            $this->guestResolveService->execute($event, $guestData, $guestIdentifier);

            return $resumed->load('guest');
        }

        return DB::transaction(function () use ($event, $guestData, $items, $message, $guestIdentifier, $anonymous, $inPerson, $fulfillment, $resumed) {
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

            // The same checkout opened again (the page reloaded, say): still
            // one order, at today's prices.
            if ($resumed) {
                $resumed->update(['total_amount' => $totalAmount, 'message' => $message, 'is_anonymous' => $anonymous]);
                $resumed->items()->delete();
                $resumed->items()->createMany($orderItems);

                return $resumed->setRelation('guest', $guest);
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
     * @param  array{name?: ?string, whatsapp?: ?string, email?: ?string, cpf?: ?string}  $guestData
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

        $resumed = $this->resumeOpenOrder($event, $guestIdentifier, fn (Order $order) => $order->is_free_amount
            && (float) $order->total_amount === round($amount, 2));

        return DB::transaction(function () use ($event, $guestData, $amount, $message, $guestIdentifier, $anonymous, $resumed) {
            $guest = $this->guestResolveService->execute($event, $guestData, $guestIdentifier);

            if ($resumed) {
                if ($resumed->status === Order::STATUS_PENDING && blank($resumed->payment_id)) {
                    $resumed->update(['message' => $message, 'is_anonymous' => $anonymous]);
                }

                return $resumed->setRelation('guest', $guest);
            }

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

    /**
     * One open order per guest: a checkout opened again — after a reload, or
     * after closing it — picks up the same order instead of leaving another
     * pending one behind.
     *
     * - The same gift, still waiting: that order is resumed — with its open
     *   payment (the same Pix to pay), if one was started. Mercado Pago is
     *   asked first how that payment is doing: an expired Pix starts over,
     *   one paid meanwhile comes back paid. So does the same gift paid just
     *   now (within the abandon time).
     * - Another gift: the guest changed their mind, so an order of theirs
     *   with no payment started is closed. One with a payment started is
     *   left alone (it may still be paid), for the abandon check to close.
     *
     * @param  Closure(Order): bool  $isSameGift
     */
    private function resumeOpenOrder(Event $event, ?string $guestIdentifier, Closure $isSameGift): ?Order
    {
        $guest = $guestIdentifier ? $event->guests()->where('identifier', $guestIdentifier)->first() : null;

        if (! $guest) {
            return null;
        }

        // Paid just now counts too: a guest who paid the Pix in their bank's
        // app still has the gift in the cart, and must not pay it twice.
        $open = $guest->orders()
            ->where('fulfillment', Order::FULFILLMENT_ONLINE)
            ->where(fn ($query) => $query
                ->where('status', Order::STATUS_PENDING)
                ->orWhere(fn ($query) => $query
                    ->where('status', Order::STATUS_PAID)
                    ->where('paid_at', '>=', now()->subMinutes(Order::ABANDONED_AFTER_MINUTES))))
            ->with('items')
            ->latest('id')
            ->get();

        $resumed = null;

        foreach ($open as $order) {
            /** @var Order $order */
            if ($order->status === Order::STATUS_PAID) {
                $resumed ??= $isSameGift($order) ? $order : null;

                continue;
            }

            if (! $isSameGift($order) || $resumed) {
                if (blank($order->payment_id)) {
                    $order->update(['status' => Order::STATUS_EXPIRED]);
                }

                continue;
            }

            if (filled($order->payment_id)) {
                $this->paymentUpdateService->execute($event, (string) $order->payment_id);
                $order->refresh();

                if (! in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_PAID], true)) {
                    continue;
                }
            }

            $resumed = $order;
        }

        return $resumed;
    }

    /**
     * @param  array<int, array{event_product_id: int, quantity: int}>  $items
     */
    private function itemsKey(array $items): string
    {
        return collect($items)
            ->mapWithKeys(fn (array $item) => [(int) $item['event_product_id'] => (int) $item['quantity']])
            ->sortKeys()
            ->toJson();
    }
}
