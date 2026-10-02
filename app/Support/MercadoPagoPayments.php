<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Payment\PaymentRefundClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Resources\Payment;

/**
 * What keeps a gift or the Premium from being paid twice: Pix codes that
 * die quickly, cancelling a payment left open before a new one is made,
 * and refunding one that went through anyway. Works for the hosts'
 * accounts and the platform's alike — the account comes in the options.
 */
class MercadoPagoPayments
{
    /** How long a Pix code can be paid. */
    public const PIX_EXPIRATION_MINUTES = 15;

    public function __construct(
        protected PaymentClient $paymentClient,
        protected PaymentRefundClient $refundClient,
    ) {}

    /**
     * Pix codes otherwise stay payable for long after the guest gave up on
     * them — and could still be paid next to a newer one.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public static function withPixExpiration(array $request): array
    {
        if (($request['payment_method_id'] ?? null) !== 'pix') {
            return $request;
        }

        return [
            ...$request,
            'date_of_expiration' => now()->addMinutes(self::PIX_EXPIRATION_MINUTES)->format('Y-m-d\TH:i:s.vP'),
        ];
    }

    /**
     * Creates the payment. Should Mercado Pago refuse the Pix expiration
     * (its format or limits — only checkable in production, where Pix
     * works), it's tried again without it: Pix keeps working, with
     * Mercado Pago's default validity, and the log tells what to fix.
     *
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $context  for the log
     *
     * @throws MPApiException when the payment itself is refused
     */
    public function create(array $request, RequestOptions $options, array $context = []): Payment
    {
        try {
            return $this->paymentClient->create($request, $options);
        } catch (MPApiException $exception) {
            $content = json_encode($exception->getApiResponse()->getContent()) ?: '';

            if (! array_key_exists('date_of_expiration', $request) || ! str_contains(strtolower($content), 'expiration')) {
                throw $exception;
            }

            Log::warning('Mercado Pago: expiração do Pix recusada; Pix criado sem prazo', [
                ...$context,
                'date_of_expiration' => $request['date_of_expiration'],
                'status_code' => $exception->getApiResponse()->getStatusCode(),
                'content' => $exception->getApiResponse()->getContent(),
            ]);

            unset($request['date_of_expiration']);

            return $this->paymentClient->create($request, $options);
        }
    }

    /**
     * Cancels a payment still waiting to be paid, so its Pix code or boleto
     * stops working. False when Mercado Pago refuses — typically because it
     * was paid in the meantime.
     *
     * @param  array<string, mixed>  $context  for the log
     */
    public function cancel(string $paymentId, RequestOptions $options, array $context = []): bool
    {
        try {
            $this->paymentClient->cancel((int) $paymentId, $options);

            return true;
        } catch (MPApiException $exception) {
            Log::warning('Mercado Pago: não foi possível cancelar o pagamento em aberto', [
                ...$context,
                'payment_id' => $paymentId,
                'status_code' => $exception->getApiResponse()->getStatusCode(),
                'content' => $exception->getApiResponse()->getContent(),
            ]);

            return false;
        }
    }

    /**
     * Gives back a payment made twice for the same thing, in full.
     *
     * @param  array<string, mixed>  $context  for the log
     */
    public function refundDuplicate(string $paymentId, RequestOptions $options, array $context = []): bool
    {
        try {
            $this->refundClient->refundTotal((int) $paymentId, $options);

            Log::warning('Mercado Pago: pagamento em dobro estornado', [...$context, 'payment_id' => $paymentId]);

            return true;
        } catch (MPApiException $exception) {
            Log::error('Mercado Pago: pagamento em dobro NÃO estornado — estorne manualmente', [
                ...$context,
                'payment_id' => $paymentId,
                'status_code' => $exception->getApiResponse()->getStatusCode(),
                'content' => $exception->getApiResponse()->getContent(),
            ]);

            return false;
        }
    }
}
