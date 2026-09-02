<?php

namespace App\Services;

use App\Support\SaleStatus;
use Illuminate\Support\Facades\DB;

class SalesAnalyticsQuery
{
    public function periodSummary(int $storeId, string $from, string $to): object
    {
        return DB::table('ventas')
            ->where('tienda_id', $storeId)
            ->where('estado', SaleStatus::COMPLETED)
            ->whereBetween('fecha', [$from, $to])
            ->selectRaw('COUNT(*) AS sales_count, COALESCE(SUM(total), 0) AS sales_total')
            ->first();
    }

    public function comparisonTotals(
        int $storeId,
        string $today,
        string $yesterday,
        string $currentFrom,
        string $currentTo,
        string $previousFrom,
        string $previousTo
    ): object {
        $minimumDate = min($yesterday, $currentFrom, $previousFrom);
        $maximumDate = max($today, $currentTo, $previousTo);

        return DB::table('ventas')
            ->where('tienda_id', $storeId)
            ->where('estado', SaleStatus::COMPLETED)
            ->whereBetween('fecha', [$minimumDate, $maximumDate])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN fecha = ? THEN total ELSE 0 END), 0) AS today_total,
                 COALESCE(SUM(CASE WHEN fecha = ? THEN total ELSE 0 END), 0) AS yesterday_total,
                 COALESCE(SUM(CASE WHEN fecha BETWEEN ? AND ? THEN total ELSE 0 END), 0) AS current_total,
                 COALESCE(SUM(CASE WHEN fecha BETWEEN ? AND ? THEN total ELSE 0 END), 0) AS previous_total',
                [$today, $yesterday, $currentFrom, $currentTo, $previousFrom, $previousTo]
            )
            ->first();
    }

    public function paymentTotals(int $storeId, string $from, string $to)
    {
        return DB::query()->fromSub(
            DB::table('pagos_venta')
                ->join('ventas', 'pagos_venta.venta_id', '=', 'ventas.id')
                ->where('ventas.tienda_id', $storeId)
                ->where('ventas.estado', SaleStatus::COMPLETED)
                ->whereBetween('ventas.fecha', [$from, $to])
                ->select(
                    'ventas.id as venta_id',
                    'pagos_venta.metodo',
                    'ventas.vuelto',
                    DB::raw('SUM(pagos_venta.monto) as monto')
                )
                ->groupBy('ventas.id', 'pagos_venta.metodo', 'ventas.vuelto'),
            'pagos_agrupados'
        )->select(
            'metodo',
            DB::raw("SUM(monto - CASE WHEN metodo = 'efectivo' THEN vuelto ELSE 0 END) as total")
        )->groupBy('metodo')->orderByDesc('total')->get();
    }

    public function dailyTotals(int $storeId, string $from, string $to)
    {
        return DB::table('ventas')
            ->where('tienda_id', $storeId)
            ->where('estado', SaleStatus::COMPLETED)
            ->whereBetween('fecha', [$from, $to])
            ->select('fecha', DB::raw('COUNT(*) AS cantidad'), DB::raw('SUM(total) AS total'))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();
    }
}
