<?php

namespace App\Console\Commands;

use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use App\Services\Events\EventDestroyService;
use App\Services\Guests\GuestDestroyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Empties the trash: whatever has been there for longer than the retention
 * period is deleted for good — except anything tied to a paid order, which
 * stays (hidden) as part of the financial history.
 */
#[Signature('trash:purge {--days=30 : Days an item stays in the trash}')]
#[Description('Exclui definitivamente o que está na lixeira há mais tempo que o prazo')]
class TrashPurgeCommand extends Command
{
    public function handle(EventDestroyService $eventDestroyService, GuestDestroyService $guestDestroyService): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $paid = fn ($query) => $query->where('status', Order::STATUS_PAID);

        $events = Event::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->whereDoesntHave('orders', $paid)
            ->get()
            ->each(fn (Event $event) => $eventDestroyService->execute($event));

        $guests = Guest::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->whereDoesntHave('orders', $paid)
            ->get()
            ->each(fn (Guest $guest) => $guestDestroyService->execute($guest));

        $products = EventProduct::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->whereDoesntHave('orderItems')
            ->where('quantity_purchased', 0)
            ->get()
            ->each(fn (EventProduct $product) => $product->forceDelete());

        $this->info("Excluídos definitivamente: {$events->count()} evento(s), {$guests->count()} convidado(s), {$products->count()} presente(s).");

        return self::SUCCESS;
    }
}
