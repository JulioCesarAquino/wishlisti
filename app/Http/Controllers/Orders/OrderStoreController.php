<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\OrderStoreRequest;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use App\Services\Orders\OrderStoreService;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cookie;

class OrderStoreController extends Controller
{
    public function __invoke(
        OrderStoreRequest $request,
        Event $event,
        OrderStoreService $service,
        CookieJar $cookies,
    ): JsonResponse {
        abort_unless($event->isViewableBy($request->user()), 404);

        if ($event->is_demo) {
            return $this->demoOrder($request, $event);
        }

        $guestIdentifier = Guest::identifierFrom($request, $event);

        $order = $service->execute(
            event: $event,
            guestData: $request->validated('guest', []),
            items: $request->validated('items') ?? [],
            message: $request->validated('message'),
            guestIdentifier: is_string($guestIdentifier) ? $guestIdentifier : null,
            anonymous: $request->boolean('anonymous'),
            fulfillment: $request->validated('fulfillment', Order::FULFILLMENT_ONLINE),
            freeAmount: $request->filled('free_amount') ? (float) $request->validated('free_amount') : null,
        );

        Cookie::queue($cookies->make(
            Guest::cookieName($event),
            $order->guest->identifier,
            60 * 24 * 365 * 5,
        ));

        return response()->json([
            'order' => [
                'id' => $order->id,
                'total_amount' => (float) $order->total_amount,
                'fulfillment' => $order->fulfillment,
                'is_anonymous' => $order->is_anonymous,
                // A resumed order: its payment already started (the Pix to
                // pay) or done — the page shows it instead of a new form.
                'status' => $order->status,
                'payment_id' => $order->payment_id,
            ],
        ]);
    }

    /**
     * On a demo, the gift the visitor picked, as if it were saved: the page
     * goes on to a pretend checkout (or the reservation's thank-you), and
     * nothing is stored or sent to Mercado Pago.
     */
    private function demoOrder(OrderStoreRequest $request, Event $event): JsonResponse
    {
        $fulfillment = $request->validated('fulfillment', Order::FULFILLMENT_ONLINE);

        abort_unless($fulfillment === Order::FULFILLMENT_ONLINE ? $event->acceptsOnlineGifts() : $event->acceptsInPersonGifts(), 422);

        if ($request->filled('free_amount')) {
            $total = (float) $request->validated('free_amount');
        } else {
            $quantities = collect($request->validated('items'))->pluck('quantity', 'event_product_id');
            $total = (float) $event->products()
                ->whereKey($quantities->keys())
                ->get(['id', 'price'])
                ->sum(fn ($product) => (float) $product->price * (int) $quantities[$product->id]);
        }

        return response()->json([
            'order' => [
                'id' => 0,
                'total_amount' => round($total, 2),
                'fulfillment' => $fulfillment,
                'is_anonymous' => $request->boolean('anonymous'),
                'status' => Order::STATUS_PENDING,
                'payment_id' => null,
            ],
        ]);
    }
}
