<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuestCheckoutRequest;
use App\Models\Tienda;
use App\Services\GuestCheckoutManager;
use App\Services\OnlineOrderCreator;
use App\Services\PublicCartManager;
use App\Support\OnlineOrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class GuestCheckoutController extends Controller
{
    public function store(
        StoreGuestCheckoutRequest $request,
        GuestCheckoutManager $checkout,
        PublicCartManager $cart,
        OnlineOrderCreator $orders,
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

        if ($request->boolean('confirmar_pedido')) {
            $operationId = $checkout->orderOperationId($store, $request->session());
            $order = $orders->create(
                $store,
                $cart->snapshot($store, $request->session()),
                $checkout->snapshot($store, $request->session()),
                $operationId,
            );

            $cart->clear($store, $request->session());
            $checkout->clear($store, $request->session());
            $checkout->rotateOrderOperation($store, $request->session());

            return redirect()->route('storefront.transfer.show', [
                'slug' => $store->slug,
                'operation' => $order->operacion_id,
            ])->with('online_order', [
                'numero' => $order->numero,
                'estado' => OnlineOrderStatus::label($order->estado),
                'total' => $order->simbolo_moneda.' '.number_format((float) $order->total, 2),
                'reserva_expira' => $order->reserva_expira_at?->format('H:i'),
            ]);
        }

        return redirect($store->publicUrl().'#checkout')
            ->with('checkout_success', 'Datos de contacto y entrega guardados de forma segura.');
    }
}
