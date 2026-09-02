<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuestCheckoutRequest;
use App\Models\Tienda;
use App\Services\GuestCheckoutManager;
use App\Services\PublicCartManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class GuestCheckoutController extends Controller
{
    public function store(
        StoreGuestCheckoutRequest $request,
        GuestCheckoutManager $checkout,
        PublicCartManager $cart,
        string $slug,
    ): RedirectResponse {
        $store = Tienda::query()->where('slug', $slug)->firstOrFail();
        abort_unless($store->estaPublicada(), 404);

        if ($cart->snapshot($store, $request->session())['items']->isEmpty()) {
            throw ValidationException::withMessages([
                'carrito' => 'Agrega al menos un producto disponible antes de continuar.',
            ]);
        }

        $checkout->save($store, $request->session(), $request->validated());

        return redirect($store->publicUrl().'#checkout')
            ->with('checkout_success', 'Datos de contacto y entrega guardados de forma segura.');
    }
}
