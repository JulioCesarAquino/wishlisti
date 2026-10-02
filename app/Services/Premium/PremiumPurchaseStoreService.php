<?php

namespace App\Services\Premium;

use App\Models\Events\Event;
use App\Models\Premium\PremiumPurchase;
use App\Models\User;
use App\Support\MercadoPagoPayments;
use App\Support\MercadoPagoPlatform;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use MercadoPago\Exceptions\MPApiException;

class PremiumPurchaseStoreService
{
    public function __construct(
        protected PremiumPurchaseUpdateService $updateService,
        protected MercadoPagoPayments $payments,
    ) {}

    /**
     * Charges the premium plan for the event on the platform's Mercado Pago
     * account, from the Payment Brick's `formData`. The amount always comes
     * from config, never from the browser.
     *
     * @param  array<string, mixed>  $formData
     */
    public function execute(Event $event, User $host, array $formData): PremiumPurchase
    {
        if (! MercadoPagoPlatform::isConfigured()) {
            throw ValidationException::withMessages([
                'formData' => 'O pagamento do Premium está indisponível no momento. Fale com o administrador.',
            ]);
        }

        $this->closeOpenPurchases($event);

        if ($this->updateService->hasEverything($event->refresh())) {
            throw ValidationException::withMessages([
                'formData' => 'Este evento já tem todos os recursos Premium.',
            ]);
        }

        $purchase = PremiumPurchase::create([
            'event_id' => $event->id,
            'user_id' => $host->id,
            'amount' => config('premium.price'),
            'status' => PremiumPurchase::STATUS_PENDING,
        ]);

        $request = MercadoPagoPayments::withPixExpiration([
            ...$formData,
            'transaction_amount' => (float) $purchase->amount,
            'description' => "Wishlisti Premium - {$event->title}",
            'external_reference' => $purchase->externalReference(),
        ]);

        // Mercado Pago rejects the payment if notification_url isn't
        // publicly reachable (e.g. localhost in local dev).
        $notificationUrl = route('premium.mercadopago-webhook');

        if (! in_array(parse_url($notificationUrl, PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
            $request['notification_url'] = $notificationUrl;
        }

        try {
            $payment = $this->payments->create($request, MercadoPagoPlatform::requestOptions(), ['premium_purchase_id' => $purchase->id]);
        } catch (MPApiException $exception) {
            Log::error('Mercado Pago: falha ao cobrar o Premium', [
                'premium_purchase_id' => $purchase->id,
                'status_code' => $exception->getApiResponse()->getStatusCode(),
                'content' => $exception->getApiResponse()->getContent(),
            ]);

            $purchase->update(['status' => PremiumPurchase::STATUS_FAILED]);

            throw ValidationException::withMessages([
                'formData' => 'Não foi possível processar o pagamento. Tente novamente.',
            ]);
        }

        // The Pix code or boleto page, to go back to while it's open.
        $paymentUrl = data_get($payment, 'point_of_interaction.transaction_data.ticket_url')
            ?? data_get($payment, 'transaction_details.external_resource_url');

        $purchase->update(['payment_url' => is_string($paymentUrl) ? $paymentUrl : null]);

        $this->updateService->applyPayment($payment);

        return $purchase->refresh();
    }

    /**
     * Paying again while a payment is still open (a Pix not paid yet, say)
     * cancels it first, so both can't be paid. If Mercado Pago won't cancel
     * it, it was most likely paid meanwhile: that's applied instead.
     */
    private function closeOpenPurchases(Event $event): void
    {
        $open = PremiumPurchase::where('event_id', $event->id)
            ->where('status', PremiumPurchase::STATUS_PENDING)
            ->whereNotNull('payment_id')
            ->get();

        foreach ($open as $purchase) {
            if ($this->payments->cancel((string) $purchase->payment_id, MercadoPagoPlatform::requestOptions(), ['premium_purchase_id' => $purchase->id])) {
                $purchase->update(['status' => PremiumPurchase::STATUS_CANCELLED]);

                continue;
            }

            $this->updateService->execute((string) $purchase->payment_id);

            if ($purchase->refresh()->status === PremiumPurchase::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'formData' => 'Ainda há um pagamento em andamento. Tente de novo em alguns minutos.',
                ]);
            }
        }
    }
}
