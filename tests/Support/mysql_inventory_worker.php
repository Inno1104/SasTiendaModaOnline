<?php

use App\Models\StockMovimiento;
use App\Services\InventoryMovementService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$variantId = (int) ($argv[1] ?? 0);
$storeId = (int) ($argv[2] ?? 0);
$quantity = (int) ($argv[3] ?? 1);

$result = DB::transaction(function () use ($variantId, $storeId, $quantity): string {
    $service = app(InventoryMovementService::class);
    $variant = $service->lockVariants([$variantId], $storeId)->get($variantId);
    if (! $variant || $variant->stock < $quantity) {
        return 'rejected';
    }

    usleep(250000);
    $service->setStock(
        $variant,
        $variant->stock - $quantity,
        StockMovimiento::TIPO_VENTA,
        null,
        null,
        'Prueba de concurrencia MySQL'
    );

    return 'sold';
}, 3);

echo $result;
