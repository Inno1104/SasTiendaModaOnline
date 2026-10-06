<?php

namespace App\Http\Controllers;

use App\Exceptions\ProductPlanLimitReached;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\ProductoManager;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Producto::class, 'producto');
    }

    public function index(ProductIndexRequest $request)
    {
        $filters = $request->validated();
        $products = Producto::query()
            ->with(['categoria:id,nombre', 'marca:id,nombre'])
            ->withSum('variantes as stock_total', 'stock')
            ->withCount([
                'variantes as variantes_stock_bajo' => fn ($query) => $query
                    ->conStockBajo(),
            ])
            ->when($filters['buscar'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $like = '%'.$search.'%';
                    $query->where('nombre', 'like', $like)
                        ->orWhere('codigo', 'like', $like)
                        ->orWhereHas('variantes', fn ($variants) => $variants->where('codigo_barra', 'like', $like));
                });
            })
            ->when($filters['categoria_id'] ?? null, fn ($query, $id) => $query->where('categoria_id', $id))
            ->when($filters['genero'] ?? null, fn ($query, $gender) => $query->where('genero', $gender))
            ->when($filters['tipo'] ?? null, fn ($query, $type) => $query->where('tipo', $type))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);
        $categories = Categoria::where('activo', true)->orderBy('nombre')->get();

        return view('productos.index', ['productos' => $products, 'categorias' => $categories]);
    }

    public function create()
    {
        $categorias = Categoria::where('activo', true)->get();
        $marcas = Marca::where('activo', true)->orderBy('nombre')->get();
        $proveedores = Proveedor::activos()->orderBy('nombre')->get();

        return view('productos.create', compact('categorias', 'marcas', 'proveedores'));
    }

    public function store(StoreProductoRequest $request, ProductoManager $manager)
    {
        try {
            $gallery = collect($request->file('imagenes', []))->map(function ($item, $index) use ($request) {
                return ['archivo' => $item['archivo'] ?? null, 'talla' => $request->input("imagenes.$index.talla"), 'color' => $request->input("imagenes.$index.color")];
            })->filter(fn ($item) => $item['archivo'])->values()->all();
            $manager->create(
                $request->safe()->except(['imagen', 'imagenes']),
                $request->user(),
                $request->file('imagen'),
                $gallery
            );
        } catch (ProductPlanLimitReached $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('productos.index')->with('success', 'Producto creado exitosamente.');
    }

    public function edit(Producto $producto)
    {
        $categorias = Categoria::query()
            ->where(fn ($query) => $query
                ->where('activo', true)
                ->orWhere('id', $producto->categoria_id))
            ->orderBy('nombre')
            ->get();
        $marcas = Marca::query()
            ->where(fn ($query) => $query
                ->where('activo', true)
                ->orWhere('id', $producto->marca_id))
            ->orderBy('nombre')
            ->get();
        $proveedores = Proveedor::query()
            ->where(fn ($query) => $query
                ->where('activo', true)
                ->orWhere('id', $producto->proveedor_id))
            ->orderBy('nombre')
            ->get();
        $producto->load('variantes', 'imagenes');

        return view('productos.edit', compact('producto', 'categorias', 'marcas', 'proveedores'));
    }

    public function update(UpdateProductoRequest $request, Producto $producto, ProductoManager $manager)
    {
        try {
            $gallery = collect($request->file('imagenes', []))->map(function ($item, $index) use ($request) {
                return ['archivo' => $item['archivo'] ?? null, 'talla' => $request->input("imagenes.$index.talla"), 'color' => $request->input("imagenes.$index.color")];
            })->filter(fn ($item) => $item['archivo'])->values()->all();
            $existingImages = collect((array) $request->input('imagenes_existentes', []))->map(function ($item, $index) use ($request) {
                return [
                    'id' => (int) ($item['id'] ?? 0),
                    'eliminar' => (bool) ($item['eliminar'] ?? false),
                    'archivo' => $request->file("imagenes_existentes.$index.archivo"),
                ];
            })->values()->all();
            $manager->update(
                $producto,
                $request->safe()->except(['imagen', 'imagenes', 'imagenes_existentes']),
                $request->user(),
                $request->file('imagen'),
                $gallery,
                $existingImages
            );
        } catch (ProductPlanLimitReached $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('productos.index')->with('success', 'Producto actualizado.');
    }

    public function destroy(Producto $producto, Request $request, ProductoManager $manager)
    {
        $manager->deactivate($producto, $request->user());

        return back()->with('success', 'Producto desactivado.');
    }

    public function show(Producto $producto)
    {
        $producto->load('variantes', 'categoria', 'marca', 'proveedor');

        return view('productos.show', compact('producto'));
    }
}
