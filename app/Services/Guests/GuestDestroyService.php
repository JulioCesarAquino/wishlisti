<?php

namespace App\Services\Guests;

use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuestDestroyService
{
    /**
     * Deletes the guest for good, along with orders of theirs that never
     * got paid (abandoned checkouts). A guest with a paid order is part of
     * the financial history and is refused — anonymize them instead.
     */
    public function execute(Guest $guest): void
    {
        if ($guest->hasPaidOrders()) {
            throw ValidationException::withMessages([
                'guest' => 'Este convidado tem presentes pagos e não pode ser excluído. Anonimize os dados dele se for preciso.',
            ]);
        }

        DB::transaction(function () use ($guest): void {
            $guest->orders()->where('status', '!=', Order::STATUS_PAID)->delete();
            $guest->forceDelete();
        });
    }
}
