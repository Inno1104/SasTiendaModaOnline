<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\DashboardCache;
use App\Services\DashboardPeriod;
use App\Services\KpiComparison;
use App\Services\SalesAnalyticsQuery;
use App\Services\StockStatistics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        DashboardPeriod $periods,
        KpiComparison $comparison,
        DashboardCache $cache,
        SalesAnalyticsQuery $analytics,
        StockStatistics $stockStatistics
    ) {
        $filters = $request->validate([
            'periodo' => ['nullable', Rule::in(['hoy', '7_dias', '30_dias', 'mes_actual', 'mes_anterior', 'personalizado'])],
            'desde' => ['nullable', 'required_if:periodo,personalizado', 'date'],
            'hasta' => ['nullable', 'required_if:periodo,personalizado', 'date', 'after_or_equal:desde'],
        ]);

        $periodo = $periods->resolve(
            $filters['periodo'] ?? '7_dias',
            $filters['desde'] ?? null,
            $filters['hasta'] ?? null
        );

        if ($periodo['start']->diffInDays($periodo['end']) > 90) {
            return back()->withErrors(['hasta' => 'El periodo personalizado no puede superar 90 días.'])->withInput();
        }

        $tiendaId = (int) auth()->user()->tienda_id;
        $data = $cache->remember(
            $tiendaId,
            $periodo,
            fn () => $this->loadDashboardData($periodo, $tiendaId, $comparison, $analytics, $stockStatistics)
        );

        return view('dashboard.index', ['periodo' => $periodo] + $data);
    }

    private function loadDashboardData(
        array $periodo,
        int $tiendaId,
        KpiComparison $comparison,
        SalesAnalyticsQuery $analytics,
        StockStatistics $stockStatistics
    ): array {
        $rango = [$periodo['start']->toDateString(), $periodo['end']->toDateString()];
        $rangoAnterior = [$periodo['previous_start']->toDateString(), $periodo['previous_end']->toDateString()];

        $salesKpis = $analytics->comparisonTotals(
            $tiendaId,
            Carbon::today()->toDateString(),
            Carbon::yesterday()->toDateString(),
            $rango[0],
            $rango[1],
            $rangoAnterior[0],
            $rangoAnterior[1]
        );
        $ventasHoy = (float) $salesKpis->today_total;
        $ventasAyer = (float) $salesKpis->yesterday_total;
        $ventasPeriodo = (float) $salesKpis->current_total;
        $ventasPeriodoAnterior = (float) $salesKpis->previous_total;

        $customerKpis = Cliente::where('activo', true)
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw('SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS new_count', [$periodo['start'], $periodo['end']])
            ->first();
        $productKpis = Producto::where('activo', true)
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw('SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS new_count', [$periodo['start'], $periodo['end']])
            ->first();
        $totalClientes = (int) $customerKpis->total_count;
        $totalProductos = (int) $productKpis->total_count;
        $clientesNuevosPeriodo = (int) $customerKpis->new_count;
        $productosNuevosPeriodo = (int) $productKpis->new_count;
        $comparacionVentasHoy = $comparison->between((float) $ventasHoy, (float) $ventasAyer, 'ayer');
        $comparacionVentasPeriodo = $comparison->between((float) $ventasPeriodo, (float) $ventasPeriodoAnterior, 'el periodo anterior');

        $stockKpis = $stockStatistics->dashboard($tiendaId);
        $stockBajo = $stockKpis['productos_bajo'];
        $variantesStockBajo = $stockKpis['variantes_bajo'];
        $variantesAgotadas = $stockKpis['variantes_agotadas'];

        $ventasDiarias = Venta::where('estado', 'completada')
            ->whereBetween('fecha', $rango)
            ->select(DB::raw('DATE(fecha) as dia'), DB::raw('SUM(total) as total'))
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->keyBy('dia');

        [$labels, $data] = $this->buildDailySeries($periodo['start'], $periodo['end'], $ventasDiarias);

        $topProductos = DB::table('detalle_ventas')
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->where('ventas.estado', 'completada')
            ->whereBetween('ventas.fecha', $rango)
            ->when($tiendaId, fn ($query) => $query->where('ventas.tienda_id', $tiendaId))
            ->select('productos.nombre', DB::raw('SUM(detalle_ventas.cantidad) as total_vendido'))
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('total_vendido')
            ->limit(5)
            ->get();

        $ventasPorCategoria = DB::table('detalle_ventas')
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->join('categorias', 'productos.categoria_id', '=', 'categorias.id')
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->where('ventas.estado', 'completada')
            ->whereBetween('ventas.fecha', $rango)
            ->when($tiendaId, fn ($query) => $query->where('ventas.tienda_id', $tiendaId))
            ->select('categorias.nombre', DB::raw('SUM(detalle_ventas.subtotal) as total'))
            ->groupBy('categorias.id', 'categorias.nombre')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $metodos = $analytics->paymentTotals($tiendaId, $rango[0], $rango[1]);

        $stockCat = DB::table('producto_variantes')
            ->join('productos', 'producto_variantes.producto_id', '=', 'productos.id')
            ->join('categorias', 'productos.categoria_id', '=', 'categorias.id')
            ->when($tiendaId, fn ($query) => $query->where('productos.tienda_id', $tiendaId))
            ->where('productos.activo', true)
            ->select('categorias.nombre', DB::raw('SUM(producto_variantes.stock) as stock'))
            ->groupBy('categorias.id', 'categorias.nombre')
            ->orderByDesc('stock')
            ->limit(8)
            ->get();

        $ultimasVentas = Venta::with('cliente')
            ->whereBetween('fecha', $rango)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return [
            'ventasHoy' => $ventasHoy,
            'ventasPeriodo' => $ventasPeriodo,
            'totalClientes' => $totalClientes,
            'totalProductos' => $totalProductos,
            'comparacionVentasHoy' => $comparacionVentasHoy,
            'comparacionVentasPeriodo' => $comparacionVentasPeriodo,
            'clientesNuevosPeriodo' => $clientesNuevosPeriodo,
            'productosNuevosPeriodo' => $productosNuevosPeriodo,
            'stockBajo' => $stockBajo,
            'variantesStockBajo' => $variantesStockBajo,
            'variantesAgotadas' => $variantesAgotadas,
            'labels' => $labels,
            'data' => $data,
            'topProductos' => $topProductos,
            'ventasPorCategoria' => $ventasPorCategoria,
            'ultimasVentas' => $ultimasVentas,
            'metodoLabels' => $metodos->pluck('metodo')->map(fn ($method) => ucfirst($method)),
            'metodoData' => $metodos->pluck('total'),
            'stockCatLabels' => $stockCat->pluck('nombre'),
            'stockCatData' => $stockCat->pluck('stock'),
        ];
    }

    private function buildDailySeries(Carbon $start, Carbon $end, $sales): array
    {
        $labels = [];
        $data = [];
        $weekdays = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
        $showWeekday = $start->diffInDays($end) < 8;

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $labels[] = $showWeekday ? $weekdays[$day->dayOfWeekIso] : $day->format('d/m');
            $data[] = (float) ($sales->get($day->toDateString())->total ?? 0);
        }

        return [$labels, $data];
    }
}
