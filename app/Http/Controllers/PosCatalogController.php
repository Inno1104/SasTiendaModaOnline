<?php

namespace App\Http\Controllers;

use App\Http\Requests\PosCatalogRequest;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;

class PosCatalogController extends Controller
{
    public function index(PosCatalogRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $products = Producto::query()->where('activo', true)
            ->whereHas('categoria', fn ($query) => $query->where('activo', true))
            ->with(['categoria:id,nombre', 'marca:id,nombre', 'variantes' => fn ($query) => $query
                ->orderByRaw('CASE WHEN stock > 0 THEN 0 ELSE 1 END')
                ->orderBy('talla')
                ->orderBy('color')])
            ->when($filters['categoria_id'] ?? null, fn ($query, $id) => $query->where('categoria_id', $id))
            ->when($filters['marca_id'] ?? null, fn ($query, $id) => $query->where('marca_id', $id))
            ->when($filters['disponibles'] ?? false, fn ($query) => $query->whereHas('variantes', fn ($variants) => $variants->conExistencias()))
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(function ($builder) use ($search) {
                    $like = '%'.trim($search).'%';
                    $builder->where('nombre', 'like', $like)->orWhere('codigo', 'like', $like)
                        ->orWhereHas('variantes', fn ($variants) => $variants->where('codigo_barra', 'like', $like));
                });
            })->orderBy('nombre')->paginate(24);

        return response()->json($products->through(fn (Producto $product) => [
            'id' => $product->id,
            'codigo' => $product->codigo,
            'nombre' => $product->nombre,
            'precio' => $product->precioActual(),
            'imagen' => $product->imagen ? asset('storage/'.$product->imagen) : null,
            'categoria' => $product->categoria?->nombre,
            'marca' => $product->marca?->nombre,
            'variantes' => $product->variantes->map(fn ($variant) => [
                'id' => $variant->id,
                'talla' => $variant->talla,
                'color' => $variant->color,
                'codigo_barra' => $variant->codigo_barra,
                'stock' => $variant->stock,
                'stock_minimo' => $variant->stock_minimo,
                'estado_stock' => $variant->estadoStock(),
                'descripcion_stock' => $variant->descripcionStock(),
            ]),
        ]));
    }
}
