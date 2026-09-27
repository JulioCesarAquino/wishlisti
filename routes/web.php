<?php

use App\Http\Controllers\Events\EventShowController;
use App\Http\Controllers\Guests\RsvpStoreController;
use App\Http\Controllers\Identity\InviteRequestStoreController;
use App\Http\Controllers\Orders\OrderCancelController;
use App\Http\Controllers\Orders\OrderPaymentStoreController;
use App\Http\Controllers\Orders\OrderPaymentWebhookController;
use App\Http\Controllers\Orders\OrderStoreController;
use App\Http\Controllers\Premium\PremiumPurchaseWebhookController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::post('/solicitar-convite', InviteRequestStoreController::class)->name('invite-requests.store');
Route::post('/webhooks/mercadopago/premium', PremiumPurchaseWebhookController::class)->name('premium.mercadopago-webhook');

Route::get('/{event:slug}', EventShowController::class)->name('events.show');
Route::post('/{event:slug}/orders', OrderStoreController::class)
    ->middleware('throttle:20,1')
    ->name('orders.store');
Route::post('/{event:slug}/orders/mercadopago-webhook', OrderPaymentWebhookController::class)->name('orders.mercadopago-webhook');
Route::post('/{event:slug}/orders/{order}/mercadopago-payment', OrderPaymentStoreController::class)
    ->middleware('throttle:10,1')
    ->name('orders.mercadopago-payment')
    ->scopeBindings();
Route::post('/{event:slug}/orders/{order}/cancel', OrderCancelController::class)
    ->middleware('throttle:20,1')
    ->name('orders.cancel')
    ->scopeBindings();
Route::post('/{event:slug}/rsvp', RsvpStoreController::class)->name('rsvp.store');
