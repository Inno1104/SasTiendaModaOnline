<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelVentaRequest;
use App\Http\Requests\PosCatalogRequest;
use App\Http\Requests\ReturnVentaRequest;
use App\Http\Requests\SaleHistoryRequest;
use App\Http\Requests\SaleReceiptRequest;
use App\Http\Requests\StoreVentaRequest;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\SaleHistoryMetrics;
use App\Services\SaleHistoryQuery;
use App\Services\VentaCanceller;
use App\Services\VentaCreator;
use App\Services\VentaReturner;
use Illuminate\Validation\ValidationException;
use Throwable;

class VentaController extends Controller
{
    public function index(
        SaleHistoryRequest $request,
        SaleHistoryQuery $historyQuery,
        SaleHistoryMetrics $historyMetrics
    ) {
        $filters = $request->validated();
        $filteredSales = $historyQuery->apply(Venta::query(), $filters);
        $metrics = $historyMetrics->calculate(clone $filteredSales);
        $ventas = $filteredSales->with(['cliente', 'vendedor', 'pagos', 'caja'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);
        $vendedores = User::where('tienda_id', $request->user()->tienda_id)
            ->where('activo', true)->orderBy('name')->get(['id', 'name']);
        $cajas = Caja::latest('apertura')->limit(50)->get(['id', 'fecha', 'estado']);

        return view('ventas.index', compact('ventas', 'vendedores', 'cajas', 'metrics'));
    }

    public function create(PosCatalogRequest $request)
    {
        $this->authorize('create', Venta::class);

        $filters = $request->validated();
        $cajaAbierta = Caja::query()->where('estado', 'abierta')->exists();
        $productos = Producto::where('activo', true)
            ->whereHas('categoria', fn ($query) => $query->where('activo', true))
            ->with([
                'categoria:id,nombre',
                'marca:id,nombre',
                'variantes' => fn ($query) => $query
                    ->orderByRaw('CASE WHEN stock > 0 THEN 0 ELSE 1 END')
                    ->orderBy('talla')
                    ->orderBy('color'),
            ])
            ->when($filters['categoria_id'] ?? null, fn ($query, $id) => $query->where('categoria_id', $id))
            ->when($filters['marca_id'] ?? null, fn ($query, $id) => $query->where('marca_id', $id))
            ->when($filters['disponibles'] ?? false, fn ($query) => $query->whereHas('variantes', fn ($variants) => $variants->conExistencias()))
            ->paginate(48);
        $categorias = Categoria::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        $marcas = Marca::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);

        return view('ventas.create', compact('productos', 'categorias', 'marcas', 'cajaAbierta', 'filters'));
    }

    public function store(StoreVentaRequest $request, VentaCreator $creator)
    {
        try {
            $venta = $creator->create($request->validated(), $request->user());

            return redirect()->route('ventas.show', $venta)
                ->with('success', 'Venta registrada: '.$venta->numero_venta);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'No fue posible registrar la venta. Inténtalo nuevamente.');
        }
    }

    public function show(Venta $venta)
    {
        $this->authorize('view', $venta);
        $venta->load('tienda', 'cliente', 'vendedor', 'detalles.producto', 'pagos', 'anuladaPor', 'devueltaPor', 'reversiones.responsable', 'auditorias.user');

        return view('ventas.show', compact('venta'));
    }

    public function receipt(SaleReceiptRequest $request, Venta $venta)
    {
        $venta->load('tienda', 'cliente', 'vendedor', 'detalles.producto', 'pagos', 'anuladaPor', 'devueltaPor', 'reversiones.responsable');
        $format = $request->string('format')->toString() === 'ticket' ? 'ticket' : 'a4';
        $html = view('ventas.receipt', compact('venta', 'format'))->render();

        if (! $request->boolean('download')) {
            return response($html);
        }

        $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', $venta->numero_venta).'.html';

        return response($html)->withHeaders([
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function anular(CancelVentaRequest $request, Venta $venta, VentaCanceller $canceller)
    {
        $data = $request->validated();
        try {
            $canceller->cancel($venta, $request->user(), $data['motivo']);

            return back()->with('success', 'Venta anulada correctamente.');
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'No fue posible anular la venta. Inténtalo nuevamente.');
        }
    }

    public function devolver(ReturnVentaRequest $request, Venta $venta, VentaReturner $returner)
    {
        try {
            $returner->return($venta, $request->user(), $request->validated('motivo'));

            return back()->with('success', 'Devolución registrada correctamente.');
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'No fue posible procesar la devolución. Inténtalo nuevamente.');
        }
    }
}
