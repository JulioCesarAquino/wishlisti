<?php

namespace App\Console\Commands;

use App\Services\Orders\OrderExpireAbandonedService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire-abandoned')]
#[Description('Fecha os pedidos online que ficaram sem pagamento (o convidado desistiu)')]
class OrdersExpireAbandonedCommand extends Command
{
    public function handle(OrderExpireAbandonedService $service): int
    {
        $this->info("{$service->execute()} pedido(s) fechado(s).");

        return self::SUCCESS;
    }
}
