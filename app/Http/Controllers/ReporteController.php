<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryReportRequest;
use App\Http\Requests\SalesReportRequest;
use App\Models\Categoria;
use App\Models\Venta;
use App\Services\InventoryReportQuery;
use App\Services\SalesAnalyticsQuery;
use Carbon\CarbonImmutable;

class ReporteController extends Controller
{
    public function ventas(SalesReportRequest $request, SalesAnalyticsQuery $analytics)
    {
        $filters = $request->validated();
        $desde = $filters['desde'];
        $hasta = $filters['hasta'];
        $storeId = (int) $request->user()->tienda_id;

        $ventas = Venta::where('estado', 'completada')
            ->whereBetween('fecha', [$desde, $hasta])
            ->with('cliente', 'vendedor', 'pagos')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(100)
            ->withQueryString();

        $summary = $analytics->periodSummary($storeId, $desde, $hasta);
        $totalGeneral = (float) $summary->sales_total;
        $totalVentas = (int) $summary->sales_count;
        $porMetodoPago = $analytics->paymentTotals($storeId, $desde, $hasta)->pluck('total', 'metodo');
        $ventasPorDia = $analytics->dailyTotals($storeId, $desde, $hasta);
        $chartData = [
            'symbol' => $request->user()->tienda->simbolo_moneda ?: '$',
            'daily' => [
                'labels' => $ventasPorDia->pluck('fecha')->map(fn ($date) => CarbonImmutable::parse($date)->format('d M')),
                'values' => $ventasPorDia->pluck('total')->map(fn ($value) => (float) $value),
            ],
            'payments' => [
                'labels' => $porMetodoPago->keys()->map(fn ($method) => ucfirst($method)),
                'values' => $porMetodoPago->values()->map(fn ($value) => (float) $value),
            ],
        ];

        return view('reportes.ventas', compact('ventas', 'totalGeneral', 'totalVentas', 'porMetodoPago', 'ventasPorDia', 'chartData', 'desde', 'hasta'));
    }

    public function inventario(InventoryReportRequest $request, InventoryReportQuery $inventory)
    {
        $filters = $request->validated();
        $productos = $inventory->build($filters)
            ->paginate($filters['por_pagina'] ?? 50)
            ->withQueryString();
        $summary = $inventory->summary($filters);
        $categorias = Categoria::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);

        return view('reportes.inventario', compact('productos', 'summary', 'categorias', 'filters'));
    }

    public function exportInventory(InventoryReportRequest $request, InventoryReportQuery $inventory)
    {
        $filters = $request->safe()->except(['page', 'por_pagina']);
        $products = $inventory->build($filters)->reorder('productos.id');

        return response()->streamDownload(function () use ($products): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Código', 'Producto', 'Categoría', 'Género', 'Variantes', 'Stock total', 'Valor inventario', 'Variantes bajas', 'Variantes agotadas']);
            foreach ($products->lazyById(500, 'productos.id', 'id') as $product) {
                $stock = (int) ($product->stock_total ?? 0);
                fputcsv($output, [
                    $this->safeCsv($product->codigo),
                    $this->safeCsv($product->nombre),
                    $this->safeCsv($product->categoria?->nombre),
                    $this->safeCsv(ucfirst((string) $product->genero)),
                    (int) $product->variantes_total,
                    $stock,
                    number_format($stock * (float) $product->precio_compra, 2, '.', ''),
                    (int) $product->variantes_bajas,
                    (int) $product->variantes_agotadas,
                ]);
            }
            fclose($output);
        }, 'inventario-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCsv(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
