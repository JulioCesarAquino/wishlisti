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

        $guestIdentifier = $request->cookie(Guest::cookieName($event));

        $order = $service->execute(
            event: $event,
            guestData: $request->validated('guest'),
            items: $request->validated('items'),
            message: $request->validated('message'),
            guestIdentifier: is_string($guestIdentifier) ? $guestIdentifier : null,
            anonymous: $request->boolean('anonymous'),
            fulfillment: $request->validated('fulfillment', Order::FULFILLMENT_ONLINE),
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
            ],
        ]);
    }
}
