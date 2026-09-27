<?php

namespace App\Services\Events;

use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use Illuminate\Support\Facades\DB;

class EventDestroyService
{
    /**
     * Deletes the event for good with everything under it — including its
     * orders, which the database otherwise refuses to let go of. Only the
     * admin gets to do this; hosts archive events that have paid orders.
     */
    public function execute(Event $event): void
    {
        DB::transaction(function () use ($event): void {
            // Order items go with their orders (cascade).
            Order::where('event_id', $event->id)->delete();
            Guest::withTrashed()->where('event_id', $event->id)->forceDelete();
            EventProduct::withTrashed()->where('event_id', $event->id)->forceDelete();
            $event->featureGrants()->delete();

            $event->forceDelete();
        });
    }
}
