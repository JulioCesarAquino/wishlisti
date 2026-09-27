<?php

namespace App\Support;

use MercadoPago\Client\Common\RequestOptions;

/**
 * The platform's own Mercado Pago account (config services.mercadopago),
 * which receives premium plan payments. Kept apart from the hosts'
 * accounts, stored per event, so the two are never mixed up.
 */
class MercadoPagoPlatform
{
    public static function isConfigured(): bool
    {
        return filled(config('services.mercadopago.access_token')) && filled(config('services.mercadopago.public_key'));
    }

    public static function publicKey(): ?string
    {
        return config('services.mercadopago.public_key');
    }

    public static function requestOptions(): RequestOptions
    {
        $options = new RequestOptions;
        $options->setAccessToken((string) config('services.mercadopago.access_token'));

        return $options;
    }
}
