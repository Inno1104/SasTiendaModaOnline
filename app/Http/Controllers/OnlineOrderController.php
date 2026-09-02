<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmOnlineOrderRequest;
use App\Models\Tienda;
use App\Services\GuestCheckoutManager;
use App\Services\OnlineOrderCreator;
use App\Services\PublicCartManager;
use App\Support\OnlineOrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class OnlineOrderController extends Controller
{
    public function store(
        ConfirmOnlineOrderRequest $request,
        OnlineOrderCreator $orders,
        PublicCartManager $cart,
        GuestCheckoutManager $checkout,
        string $slug,
    ): RedirectResponse {
        $store = Tienda::query()->where('slug', $slug)->firstOrFail();
        abort_unless($store->estaPublicada(), 404);

        $operationId = (string) $request->validated('operacion_id');
        if (! hash_equals($checkout->orderOperationId($store, $request->session()), $operationId)) {
            throw ValidationException::withMessages([
                'operacion_id' => 'La confirmación expiró. Actualiza la página e inténtalo nuevamente.',
            ]);
        }

        $order = $orders->create(
            $store,
            $cart->snapshot($store, $request->session()),
            $checkout->snapshot($store, $request->session()),
            $operationId,
        );

        $cart->clear($store, $request->session());
        $checkout->clear($store, $request->session());
        $checkout->rotateOrderOperation($store, $request->session());

        return redirect($store->publicUrl().'#pedido-confirmado')->with('online_order', [
            'numero' => $order->numero,
            'estado' => OnlineOrderStatus::label($order->estado),
            'total' => $order->simbolo_moneda.' '.number_format((float) $order->total, 2),
            'reserva_expira' => $order->reserva_expira_at?->format('H:i'),
        ]);
    }
}
