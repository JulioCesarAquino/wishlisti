<?php

use App\Enums\Premium\Feature;

/*
 * The premium plan a host buys for one of their events, paid to the
 * platform's own Mercado Pago account (see services.mercadopago) — never to
 * the host's. A purchase unlocks the event features for that event — until
 * some days after its date — and the host features for the host, with no
 * expiration.
 *
 * The price and the days below are defaults: the admin changes them on the
 * panel (Configurações), see App\Support\PlatformSettings.
 */
return [
    'price' => (float) env('PREMIUM_PRICE', 39.90),

    // The event features end this many days after the event's date.
    'grace_days' => (int) env('PREMIUM_GRACE_DAYS', 60),

    // How far the host can move a Premium event's date on their own.
    'date_window_days' => (int) env('PREMIUM_DATE_WINDOW_DAYS', 90),

    'event_features' => [Feature::Payments, Feature::GuestList, Feature::FullGiftList, Feature::Guestbook],

    'host_features' => [Feature::Contacts],

    // Without Feature::FullGiftList: at most this many gifts on the list,
    // only catalog items, one unit each.
    'free_gift_limit' => (int) env('FREE_GIFT_LIMIT', 15),
];
