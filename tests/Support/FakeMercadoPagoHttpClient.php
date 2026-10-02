<?php

namespace Tests\Support;

use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Net\MPHttpClient;
use MercadoPago\Net\MPRequest;
use MercadoPago\Net\MPResponse;

/**
 * Records every outgoing request and replays canned responses in order,
 * so payment/preference services can be tested without hitting the real
 * Mercado Pago API.
 */
class FakeMercadoPagoHttpClient implements MPHttpClient
{
    /** @var array<int, MPResponse> */
    protected array $responses;

    /** @var array<int, MPRequest> */
    public array $requests = [];

    public function __construct(MPResponse ...$responses)
    {
        $this->responses = $responses;
    }

    public function send(MPRequest $request): MPResponse
    {
        $this->requests[] = $request;

        $response = array_shift($this->responses) ?? new MPResponse(200, []);

        // Like the real client (MPDefaultHttpClient), which turns error
        // statuses into exceptions.
        if ($response->getStatusCode() >= 400) {
            throw new MPApiException('Api error. Check response for details', $response);
        }

        return $response;
    }
}
