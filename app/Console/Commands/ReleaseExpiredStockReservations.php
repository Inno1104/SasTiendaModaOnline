<?php

namespace App\Console\Commands;

use App\Services\StockReservationManager;
use Illuminate\Console\Command;

final class ReleaseExpiredStockReservations extends Command
{
    protected $signature = 'reservas:liberar-vencidas';

    protected $description = 'Libera reservas de inventario cuyo tiempo de pago ha vencido';

    public function handle(StockReservationManager $reservations): int
    {
        $released = $reservations->releaseExpired();
        $this->info("Reservas liberadas: {$released}");

        return self::SUCCESS;
    }
}
