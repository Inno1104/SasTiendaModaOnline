<?php

namespace App\Http\Controllers;

use App\Models\PedidoOnline;
use App\Models\Tienda;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

final class PublicOrderLookupController extends Controller
{
    public function show(string $slug)
    {
        $store = $this->storeContext($slug);
        return view('storefront.order-lookup', compact('store'));
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $store = $this->storeContext($slug);
        $data = $request->validate([
            'numero_pedido' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
            'cedula' => ['required', 'string', 'regex:/^[0-9]{8,13}$/'],
        ], [
            'numero_pedido.required' => 'Ingresa el número de tu pedido.',
            'numero_pedido.regex' => 'El número del pedido no tiene un formato válido.',
            'cedula.required' => 'Ingresa tu número de cédula.',
            'cedula.regex' => 'La identificación debe contener entre 8 y 13 dígitos.',
        ]);

        $order = PedidoOnline::withoutGlobalScopes()
            ->where('tienda_id', $store->id)
            ->where('numero', strtoupper(trim($data['numero_pedido'])))
            ->where('cliente_dni', $data['cedula'])
            ->first();

        if (! $order) {
            throw ValidationException::withMessages(['pedido' => 'No encontramos un pedido que coincida con los datos ingresados. Verifica la información e inténtalo nuevamente.']);
        }
        if (! $order->seguimientoClienteDisponible()) {
            throw ValidationException::withMessages(['pedido' => 'El seguimiento de este pedido ya finalizó y no se encuentra disponible.']);
        }

        return redirect()->to(URL::temporarySignedRoute('storefront.orders.tracking', now()->addDays(30), [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
        ]));
    }

    private function storeContext(string $slug): Tienda
    {
        $store = Tienda::query()->where('slug', $slug)->firstOrFail();
        abort_unless($store->estaPublicada(), 404);
        return $store;
    }
}
