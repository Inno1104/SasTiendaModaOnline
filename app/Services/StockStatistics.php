<?php

namespace App\Services;

use App\Models\ProductoVariante;
use App\Support\StockState;

final class StockStatistics
{
    /** @return array{total_unidades: int, bajo_stock: int, sin_stock: int, disponibles: int} */
    public function operational(): array
    {
        $low = StockState::lowSql();
        $exhausted = StockState::exhaustedSql();
        $available = StockState::availableSql();
        $statistics = ProductoVariante::query()
            ->operativas()
            ->selectRaw('COALESCE(SUM(stock), 0) as total_unidades')
            ->selectRaw("SUM(CASE WHEN {$low} THEN 1 ELSE 0 END) as bajo_stock")
            ->selectRaw("SUM(CASE WHEN {$exhausted} THEN 1 ELSE 0 END) as sin_stock")
            ->selectRaw("SUM(CASE WHEN {$available} THEN 1 ELSE 0 END) as disponibles")
            ->first();

        return [
            'total_unidades' => (int) $statistics->total_unidades,
            'bajo_stock' => (int) $statistics->bajo_stock,
            'sin_stock' => (int) $statistics->sin_stock,
            'disponibles' => (int) $statistics->disponibles,
        ];
    }

    /** @return array{productos_bajo: int, variantes_bajo: int, variantes_agotadas: int} */
    public function dashboard(int $storeId): array
    {
        $stock = 'producto_variantes.stock';
        $minimum = 'producto_variantes.stock_minimo';
        $low = StockState::lowSql($stock, $minimum);
        $exhausted = StockState::exhaustedSql($stock);
        $statistics = ProductoVariante::query()
            ->withoutGlobalScopes()
            ->where('producto_variantes.tienda_id', $storeId)
            ->operativas()
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$low} THEN producto_id END) AS low_products")
            ->selectRaw("SUM(CASE WHEN {$low} THEN 1 ELSE 0 END) AS low_variants")
            ->selectRaw("SUM(CASE WHEN {$exhausted} THEN 1 ELSE 0 END) AS exhausted_variants")
            ->first();

        return [
            'productos_bajo' => (int) $statistics->low_products,
            'variantes_bajo' => (int) $statistics->low_variants,
            'variantes_agotadas' => (int) $statistics->exhausted_variants,
        ];
    }
}
