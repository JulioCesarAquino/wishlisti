<?php

namespace App\Services\Premium;

use App\Models\Events\Event;
use App\Models\Premium\PremiumPurchase;
use App\Models\User;
use App\Support\MercadoPagoPlatform;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;

class PremiumPurchaseStoreService
{
    public function __construct(
        protected PaymentClient $paymentClient,
        protected PremiumPurchaseUpdateService $updateService,
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

        if ($this->updateService->hasEverything($event)) {
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

        $request = [
            ...$formData,
            'transaction_amount' => (float) $purchase->amount,
            'description' => "Wishlisti Premium - {$event->title}",
            'external_reference' => $purchase->externalReference(),
        ];

        // Mercado Pago rejects the payment if notification_url isn't
        // publicly reachable (e.g. localhost in local dev).
        $notificationUrl = route('premium.mercadopago-webhook');

        if (! in_array(parse_url($notificationUrl, PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
            $request['notification_url'] = $notificationUrl;
        }

        try {
            $payment = $this->paymentClient->create($request, MercadoPagoPlatform::requestOptions());
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

        $this->updateService->applyPayment($payment);

        return $purchase->refresh();
    }
}
