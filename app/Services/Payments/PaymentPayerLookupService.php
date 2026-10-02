<?php

namespace App\Services\Payments;

use App\Models\Orders\Order;
use App\Models\Premium\PremiumPurchase;
use App\Models\User;
use App\Services\Orders\OrderPaymentUpdateService;
use App\Support\MercadoPagoPlatform;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Resources\Payment;

/**
 * Who paid, for the admin's internal checks (an anonymous gift under
 * dispute, say). Asked of Mercado Pago when needed, never copied into the
 * database; each lookup goes in the audit trail — who looked, at what.
 */
class PaymentPayerLookupService
{
    public function __construct(
        protected PaymentClient $paymentClient,
        protected OrderPaymentUpdateService $orderPayments,
    ) {}

    /**
     * @return array{name: ?string, email: ?string, document: ?string, method: ?string}|null null when Mercado Pago can't tell
     */
    public function execute(Order|PremiumPurchase $record, User $viewer): ?array
    {
        if (blank($record->payment_id)) {
            return null;
        }

        // Logged before asking: trying to look counts too. No payer data in
        // the entry itself.
        activity('payment')
            ->performedOn($record)
            ->causedBy($viewer)
            ->event('payer_lookup')
            ->log('consultou os dados do pagador');

        $options = $record instanceof Order
            ? $this->orderPayments->requestOptions($record->event)
            : MercadoPagoPlatform::requestOptions();

        try {
            $payment = $this->paymentClient->get((int) $record->payment_id, $options);
        } catch (MPApiException $exception) {
            Log::warning('Mercado Pago: não foi possível consultar o pagador', [
                'record' => $record::class.'#'.$record->getKey(),
                'payment_id' => $record->payment_id,
                'status_code' => $exception->getApiResponse()->getStatusCode(),
            ]);

            return null;
        }

        return $this->payer($payment);
    }

    /**
     * What Mercado Pago tells varies with the payment method: cards carry
     * the cardholder; Pix often little more than an e-mail.
     *
     * @return array{name: ?string, email: ?string, document: ?string, method: ?string}
     */
    private function payer(Payment $payment): array
    {
        $fullName = trim(data_get($payment, 'payer.first_name', '').' '.data_get($payment, 'payer.last_name', ''));
        $documentType = data_get($payment, 'payer.identification.type') ?? data_get($payment, 'card.cardholder.identification.type');
        $documentNumber = data_get($payment, 'payer.identification.number') ?? data_get($payment, 'card.cardholder.identification.number');

        return [
            'name' => data_get($payment, 'card.cardholder.name') ?: ($fullName ?: null),
            'email' => data_get($payment, 'payer.email') ?: null,
            'document' => $documentNumber ? trim("{$documentType} {$documentNumber}") : null,
            'method' => data_get($payment, 'payment_method_id') ?: null,
        ];
    }
}
