<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReconcileStockCountRequest;
use App\Http\Requests\StockHistoryRequest;
use App\Http\Requests\StockIndexRequest;
use App\Http\Requests\StoreStockCountRequest;
use App\Http\Requests\UpdateStockRequest;
use App\Models\Categoria;
use App\Models\ProductoVariante;
use App\Models\StockConteo;
use App\Services\InventoryReconciliationService;
use App\Services\StockManager;
use App\Services\StockQuery;
use App\Services\StockStatistics;

class StockController extends Controller
{
    public function index(
        StockIndexRequest $request,
        StockQuery $stockQuery,
        StockStatistics $stockStatistics
    ) {
        $filters = $request->validated();
        $variants = $stockQuery->apply(ProductoVariante::query(), $filters)
            ->paginate($filters['por_pagina'] ?? 20)
            ->withQueryString();
        $statistics = $stockStatistics->operational();

        return view('stock.index', [
            'variantes' => $variants,
            'categorias' => Categoria::query()->orderBy('nombre')->get(['id', 'nombre']),
            'totalUnidades' => $statistics['total_unidades'],
            'bajoStock' => $statistics['bajo_stock'],
            'sinStock' => $statistics['sin_stock'],
            'variantesDisponibles' => $statistics['disponibles'],
        ]);
    }

    public function update(
        UpdateStockRequest $request,
        ProductoVariante $variante,
        StockManager $manager
    ) {
        $manager->adjust(
            $variante,
            $request->integer('stock'),
            $request->integer('stock_minimo'),
            $request->integer('expected_version'),
            $request->user(),
            $request->validated('motivo')
        );

        return back()->with('success', 'Stock actualizado para '.$variante->producto->nombre.'.');
    }

    public function history(StockHistoryRequest $request, ProductoVariante $variante)
    {
        $filters = $request->validated();
        $variante->load('producto.categoria');
        $movimientos = $variante->movimientos()->with('responsable:id,name')
            ->when($filters['tipo'] ?? null, fn ($query, $type) => $query->where('tipo', $type))
            ->when($filters['desde'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['hasta'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(20, ['*'], 'movimientos_page')->withQueryString();
        $conteos = $variante->conteos()->with([
            'responsable:id,name', 'autorizador:id,name', 'reemplazadoPor:id,name',
        ])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(10, ['*'], 'conteos_page')->withQueryString();

        $pendingCounts = $variante->conteos()
            ->where('estado', StockConteo::ESTADO_PENDIENTE);
        $pendingCount = (clone $pendingCounts)->count();
        $staleCount = (clone $pendingCounts)
            ->where(function ($query) use ($variante): void {
                $query->where('version_stock', '!=', $variante->lock_version)
                    ->orWhere('stock_sistema', '!=', $variante->stock);
            })
            ->count();

        return view('stock.history', compact(
            'variante', 'movimientos', 'conteos', 'filters', 'pendingCount', 'staleCount'
        ));
    }

    public function count(
        StoreStockCountRequest $request,
        ProductoVariante $variante,
        InventoryReconciliationService $reconciliation
    ) {
        $count = $reconciliation->count(
            $variante,
            $request->integer('stock_fisico'),
            $request->integer('expected_version'),
            $request->user(),
            $request->validated('motivo'),
            $request->string('operacion_id')->toString()
        );

        $message = $count->estado === StockConteo::ESTADO_CONCILIADO
            ? 'Conteo físico sin diferencias autoaprobado.'
            : 'Conteo físico registrado. El inventario aún no fue modificado.';

        return back()->with('success', $message);
    }

    public function reconcile(
        ReconcileStockCountRequest $request,
        StockConteo $conteo,
        InventoryReconciliationService $reconciliation
    ) {
        $reconciliation->reconcile($conteo, $request->user(), $request->string('motivo')->toString());

        return back()->with('success', 'Conteo conciliado y movimiento de inventario registrado.');
    }

    public function replenishment(StockIndexRequest $request)
    {
        $variants = ProductoVariante::query()->operativas()
            ->where(fn ($query) => $query->agotadas()->orWhere(fn ($low) => $low->conStockBajo()))
            ->with('producto.categoria')->orderBy('stock')->orderBy('id');

        return response()->streamDownload(function () use ($variants): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Producto', 'Código', 'Categoría', 'Talla', 'Color', 'Código de barras', 'Stock', 'Mínimo', 'Sugerido', 'Estado']);
            foreach ($variants->lazy(500) as $variant) {
                fputcsv($output, [
                    $this->safeCsv($variant->producto?->nombre),
                    $this->safeCsv($variant->producto?->codigo),
                    $this->safeCsv($variant->producto?->categoria?->nombre),
                    $this->safeCsv($variant->talla),
                    $this->safeCsv($variant->color),
                    $this->safeCsv($variant->codigo_barra),
                    $variant->stock,
                    $variant->stock_minimo,
                    max(0, $variant->stock_minimo - $variant->stock + 1),
                    $variant->estadoStock(),
                ]);
            }
            fclose($output);
        }, 'reposicion-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCsv(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
