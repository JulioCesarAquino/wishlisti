<?php

namespace App\Services\Orders;

use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\Orders\Order;
use App\Services\Guests\GuestResolveService;
use App\Support\MercadoPagoPayments;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStoreService
{
    public function __construct(
        protected GuestResolveService $guestResolveService,
        protected OrderPaymentUpdateService $paymentUpdateService,
        protected MercadoPagoPayments $payments,
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

        if (! $inPerson) {
            $this->closeRetriedOrders($event, $guestIdentifier, fn (Order $order) => ! $order->is_free_amount
                && $this->itemsKey($order->items->map(fn ($item) => ['event_product_id' => $item->event_product_id, 'quantity' => $item->quantity])->all()) === $this->itemsKey($items));
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

        $this->closeRetriedOrders($event, $guestIdentifier, fn (Order $order) => $order->is_free_amount
            && (float) $order->total_amount === round($amount, 2));

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

    /**
     * The same gift ordered again from the same browser while the previous
     * order still waits for its payment is a retry (the Pix was closed
     * unpaid, say): the previous payment is cancelled, so both can't be
     * paid. If it was paid in the meantime, the new order is refused.
     *
     * Other pending orders are left alone: a guest may well be giving two
     * different gifts.
     *
     * @param  Closure(Order): bool  $isSameGift
     */
    private function closeRetriedOrders(Event $event, ?string $guestIdentifier, Closure $isSameGift): void
    {
        $guest = $guestIdentifier ? $event->guests()->where('identifier', $guestIdentifier)->first() : null;

        if (! $guest) {
            return;
        }

        $retried = $guest->orders()
            ->where('fulfillment', Order::FULFILLMENT_ONLINE)
            ->where('status', Order::STATUS_PENDING)
            ->whereNotNull('payment_id')
            ->with('items')
            ->get()
            ->filter($isSameGift);

        $requestOptions = $this->paymentUpdateService->requestOptions($event);

        foreach ($retried as $order) {
            /** @var Order $order */
            if ($this->payments->cancel((string) $order->payment_id, $requestOptions, ['order_id' => $order->id])) {
                $order->update(['status' => Order::STATUS_CANCELLED]);

                continue;
            }

            $this->paymentUpdateService->execute($event, (string) $order->payment_id);

            $status = $order->refresh()->status;

            if (in_array($status, [Order::STATUS_PAID, Order::STATUS_PENDING], true)) {
                throw ValidationException::withMessages([
                    'items' => $status === Order::STATUS_PAID
                        ? 'Seu pagamento anterior deste presente já foi confirmado. Obrigado!'
                        : 'Ainda há um pagamento em andamento para este presente. Tente de novo em alguns minutos.',
                ]);
            }
        }
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
