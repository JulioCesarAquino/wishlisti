<?php

namespace App\Http\Controllers\Premium;

use App\Http\Controllers\Controller;
use App\Services\Premium\PremiumPurchaseUpdateService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PremiumPurchaseWebhookController extends Controller
{
    /**
     * Mercado Pago's notification for payments on the platform account.
     * Only the payment id is taken from it; the status is always re-fetched.
     */
    public function __invoke(Request $request, PremiumPurchaseUpdateService $service): Response
    {
        // PHP turns the dot in `?data.id=` into an underscore (`data_id`).
        $paymentId = $request->input('data.id')
            ?? $request->query('data_id')
            ?? $request->input('id');

        $type = $request->input('type', $request->query('type'));

        if ($type === 'payment' && filled($paymentId)) {
            $service->execute((string) $paymentId);
        }

        return response()->noContent();
    }
}
