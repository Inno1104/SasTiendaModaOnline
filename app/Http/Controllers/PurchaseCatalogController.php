<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseCatalogRequest;
use App\Models\ProductoVariante;
use App\Services\PurchaseCatalogQuery;
use Illuminate\Http\JsonResponse;

class PurchaseCatalogController extends Controller
{
    public function __invoke(PurchaseCatalogRequest $request, PurchaseCatalogQuery $catalog): JsonResponse
    {
        $variants = $catalog->paginate($request->validated());

        return response()->json($variants->through(fn (ProductoVariante $variant) => [
            'id' => $variant->id,
            'nombre' => $variant->producto->nombre,
            'codigo' => $variant->producto->codigo,
            'talla' => $variant->talla,
            'color' => $variant->color,
            'codigo_barra' => $variant->codigo_barra,
            'stock' => $variant->stock,
            'precio' => $variant->producto->precio_compra,
            'texto' => $variant->producto->nombre.' â€” '.$variant->talla.' / '.$variant->color,
        ]));
    }
}
