<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\OrderPaymentStoreRequest;
use App\Models\Events\Event;
use App\Models\Orders\Order;
use App\Services\Orders\OrderPaymentCreateService;
use Illuminate\Http\JsonResponse;

class OrderPaymentStoreController extends Controller
{
    public function __invoke(
        OrderPaymentStoreRequest $request,
        Event $event,
        Order $order,
        OrderPaymentCreateService $service,
    ): JsonResponse {
        abort_unless($event->isViewableBy($request->user()), 404);
        abort_if($order->isInPerson(), 409, 'Este presente é para entrega pessoal.');
        // A guest who left the checkout open past the abandon time and pays
        // now: the gift is still theirs to give.
        if ($order->status === Order::STATUS_EXPIRED && blank($order->payment_id)) {
            $order->update(['status' => Order::STATUS_PENDING]);
        }

        abort_if($order->status !== Order::STATUS_PENDING, 409, 'Este pedido já foi processado.');

        // formData's shape varies per payment method (card vs. Pix vs.
        // boleto), so we only validate the fields we always require and
        // forward the raw input — `validated()` would silently drop every
        // nested key (token, installments, issuer_id...) that has no rule.
        $payment = $service->execute($order, $request->input('formData'));

        return response()->json($payment);
    }
}
