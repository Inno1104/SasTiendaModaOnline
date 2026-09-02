<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddPublicCartItemRequest;
use App\Http\Requests\UpdatePublicCartItemRequest;
use App\Models\Tienda;
use App\Services\PublicCartManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PublicCartController extends Controller
{
    public function store(
        AddPublicCartItemRequest $request,
        PublicCartManager $cart,
        string $slug,
    ): RedirectResponse {
        $store = $this->publishedStore($slug);
        $cart->add(
            $store,
            $request->session(),
            (int) $request->validated('variante_id'),
            (int) $request->validated('cantidad'),
        );

        return redirect($store->publicUrl().'#carrito')->with('cart_success', 'Producto agregado al carrito.');
    }

    public function update(
        UpdatePublicCartItemRequest $request,
        PublicCartManager $cart,
        string $slug,
        int $variant,
    ): RedirectResponse {
        $store = $this->publishedStore($slug);
        $cart->update($store, $request->session(), $variant, (int) $request->validated('cantidad'));

        return redirect($store->publicUrl().'#carrito')->with('cart_success', 'Cantidad actualizada.');
    }

    public function destroy(Request $request, PublicCartManager $cart, string $slug, int $variant): RedirectResponse
    {
        $store = $this->publishedStore($slug);
        $cart->remove($store, $request->session(), $variant);

        return redirect($store->publicUrl().'#carrito')->with('cart_success', 'Producto eliminado del carrito.');
    }

    public function clear(Request $request, PublicCartManager $cart, string $slug): RedirectResponse
    {
        $store = $this->publishedStore($slug);
        $cart->clear($store, $request->session());

        return redirect($store->publicUrl().'#carrito')->with('cart_success', 'Carrito vaciado.');
    }

    private function publishedStore(string $slug): Tienda
    {
        $store = Tienda::query()->where('slug', $slug)->firstOrFail();
        abort_unless($store->estaPublicada(), 404);

        return $store;
    }
}
