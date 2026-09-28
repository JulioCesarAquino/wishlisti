<?php

use App\Enums\Premium\Feature;

/*
 * The premium plan a host buys for one of their events, paid to the
 * platform's own Mercado Pago account (see services.mercadopago) — never to
 * the host's. A purchase unlocks the event features for that event and the
 * host features for the host, with no expiration.
 */
return [
    'price' => (float) env('PREMIUM_PRICE', 39.90),

    'event_features' => [Feature::Payments, Feature::GuestList, Feature::FullGiftList, Feature::Guestbook],

    'host_features' => [Feature::Contacts],

    // Without Feature::FullGiftList: at most this many gifts on the list,
    // only catalog items, one unit each.
    'free_gift_limit' => (int) env('FREE_GIFT_LIMIT', 15),
];
