<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelCompraRequest;
use App\Http\Requests\CompraIndexRequest;
use App\Http\Requests\StoreCompraRequest;
use App\Models\Categoria;
use App\Models\Compra;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Services\PurchaseCanceller;
use App\Services\PurchaseQuery;
use App\Services\PurchaseService;
use App\Services\PurchaseTraceService;

class CompraController extends Controller
{
    public function index(CompraIndexRequest $request, PurchaseQuery $purchases)
    {
        $compras = $purchases->paginate($request->validated());
        $totalMes = Compra::whereMonth('fecha', now()->month)
            ->whereYear('fecha', now()->year)
            ->where('estado', 'recibido')
            ->sum('total');
        $proveedores = Proveedor::query()->orderBy('nombre')->get(['id', 'nombre']);

        return view('compras.index', compact('compras', 'totalMes', 'proveedores'));
    }

    public function create()
    {
        $this->authorize('create', Compra::class);

        $proveedores = Proveedor::activos()->orderBy('nombre')->get(['id', 'nombre']);
        $categorias = Categoria::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        $oldVariantIds = collect(old('items', []))->pluck('variante_id')->filter()->map(fn ($id) => (int) $id)->unique();
        $selectedVariants = ProductoVariante::query()
            ->whereIn('id', $oldVariantIds)
            ->with('producto:id,tienda_id,nombre,precio_compra')
            ->get()
            ->mapWithKeys(fn (ProductoVariante $variant) => [$variant->id => [
                'id' => $variant->id,
                'texto' => $variant->producto->nombre.' â€” '.$variant->talla.' / '.$variant->color,
                'precio' => $variant->producto->precio_compra,
            ]]);

        return view('compras.create', compact('proveedores', 'categorias', 'selectedVariants'));
    }

    public function store(StoreCompraRequest $request, PurchaseService $purchases)
    {
        $purchases->receive($request->validated(), $request->user());

        return redirect()->route('compras.index')->with('success', 'Compra registrada y stock actualizado.');
    }

    public function show(Compra $compra, PurchaseTraceService $traces)
    {
        $this->authorize('view', $compra);
        $compra->load(
            'proveedor', 'usuario', 'anuladaPor', 'detalles.producto', 'detalles.variante',
            'auditorias.user'
        );
        $trazabilidad = $traces->trace($compra);

        return view('compras.show', compact('compra', 'trazabilidad'));
    }

    public function cancel(CancelCompraRequest $request, Compra $compra, PurchaseCanceller $canceller)
    {
        $canceller->cancel($compra, $request->user(), $request->validated('motivo'));

        return back()->with('success', 'Compra anulada y stock revertido correctamente.');
    }
}
