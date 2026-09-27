<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use App\Services\Orders\OrderCancelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderCancelController extends Controller
{
    /**
     * A guest giving up on a gift they reserved. Only the guest who made the
     * reservation (recognised by their browser cookie) can do it.
     */
    public function __invoke(Request $request, Event $event, Order $order, OrderCancelService $service): RedirectResponse
    {
        abort_unless($event->isViewableBy($request->user()), 404);

        $identifier = $request->cookie(Guest::cookieName($event));

        abort_unless(is_string($identifier) && $order->guest?->identifier === $identifier, 403);

        $service->execute($order);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Reserva desfeita. O presente voltou para a lista.',
        ]);

        return back();
    }
}
