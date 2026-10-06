<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicCatalogRequest;
use App\Models\Categoria;
use App\Models\EcuadorUbicacion;
use App\Models\Producto;
use App\Models\StockReserva;
use App\Models\Tienda;
use App\Services\GuestCheckoutManager;
use App\Services\PublicCartManager;
use App\Services\PublicCatalogQuery;
use App\Support\StorefrontConfiguration;
use App\Support\StoreLocation;

final class PublicStoreController extends Controller
{
    public function show(
        PublicCatalogRequest $request,
        PublicCatalogQuery $catalog,
        PublicCartManager $cart,
        GuestCheckoutManager $checkout,
        string $slug,
    ) {
        $store = Tienda::query()->where('slug', $slug)->firstOrFail();
        abort_unless($store->estaPublicada(), 404);
        $catalogData = $catalog->execute($store, $request->validated());

        return view('storefront.show', $catalogData + [
            'store' => $store,
            'contact' => $store->publicContactData(),
            'filters' => $request->validated(),
            'cart' => $cart->snapshot($store, $request->session()),
            'checkout' => $checkout->snapshot($store, $request->session()),
            'orderOperationId' => $checkout->orderOperationId($store, $request->session()),
            'countries' => StoreLocation::COUNTRIES,
            'ecuadorLocations' => EcuadorUbicacion::catalogo(),
            'days' => StorefrontConfiguration::DAYS,
            'conditionLabels' => StorefrontConfiguration::CONDITION_FIELDS,
        ]);
    }

    public function product(
        PublicCartManager $cart,
        string $slug,
        string $code,
    ) {
        $store = Tienda::query()->where('slug', $slug)->firstOrFail();
        abort_unless($store->estaPublicada(), 404);

        $product = Producto::withoutGlobalScopes()
            ->where('tienda_id', $store->id)
            ->where('codigo', $code)
            ->where('activo', true)
            ->whereHas('categoria', fn ($query) => $query->withoutGlobalScopes()
                ->where('tienda_id', $store->id)
                ->where('activo', true))
            ->with([
                'categoria' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'tienda_id', 'nombre', 'slug'),
                'marca' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'tienda_id', 'nombre'),
                'imagenes' => fn ($query) => $query->withoutGlobalScopes()
                    ->select('id', 'tienda_id', 'producto_id', 'ruta', 'talla', 'color', 'orden', 'principal')
                    ->orderBy('orden'),
                'variantes' => fn ($query) => $query->withoutGlobalScopes()
                    ->select('id', 'tienda_id', 'producto_id', 'talla', 'detalle_talla', 'color', 'stock', 'stock_minimo')
                    ->withSum(['reservas as stock_reservado' => fn ($reservations) => $reservations
                        ->withoutGlobalScopes()
                        ->where('estado', StockReserva::ACTIVE)
                        ->where('expira_at', '>', now())], 'cantidad')
                    ->where('tienda_id', $store->id)
                    ->orderBy('color')
                    ->orderBy('talla'),
            ])
            ->firstOrFail();

        $relatedProducts = Producto::withoutGlobalScopes()
            ->where('tienda_id', $store->id)
            ->where('id', '!=', $product->id)
            ->where('activo', true)
            ->whereHas('categoria', fn ($query) => $query->withoutGlobalScopes()
                ->where('tienda_id', $store->id)
                ->where('activo', true))
            ->whereHas('variantes', fn ($query) => $query->withoutGlobalScopes()
                ->where('tienda_id', $store->id)
                ->where('stock', '>', 0))
            ->where(fn ($query) => $query
                ->where('categoria_id', $product->categoria_id)
                ->orWhere('tipo', $product->tipo)
                ->orWhere('genero', $product->genero))
            ->with([
                'categoria' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'tienda_id', 'nombre', 'slug'),
                'marca' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'tienda_id', 'nombre'),
                'imagenes' => fn ($query) => $query->withoutGlobalScopes()
                    ->select('id', 'tienda_id', 'producto_id', 'ruta', 'orden', 'principal')
                    ->orderByDesc('principal')
                    ->orderBy('orden'),
                'variantes' => fn ($query) => $query->withoutGlobalScopes()
                    ->select('id', 'tienda_id', 'producto_id', 'color', 'stock')
                    ->withSum(['reservas as stock_reservado' => fn ($reservations) => $reservations
                        ->withoutGlobalScopes()
                        ->where('estado', StockReserva::ACTIVE)
                        ->where('expira_at', '>', now())], 'cantidad')
                    ->where('tienda_id', $store->id),
            ])
            ->orderByRaw('CASE WHEN categoria_id = ? THEN 0 ELSE 1 END', [$product->categoria_id])
            ->orderByDesc('destacado')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $categories = Categoria::withoutGlobalScopes()
            ->select('id', 'tienda_id', 'nombre', 'slug')
            ->where('tienda_id', $store->id)
            ->where('activo', true)
            ->whereHas('productos', fn ($query) => $query->withoutGlobalScopes()
                ->where('tienda_id', $store->id)
                ->where('activo', true))
            ->orderBy('nombre')
            ->limit(6)
            ->get();

        return view('storefront.product', [
            'store' => $store,
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'categories' => $categories,
            'cart' => $cart->snapshot($store, request()->session()),
        ]);
    }
}
