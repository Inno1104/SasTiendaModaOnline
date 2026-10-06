<?php

namespace App\Http\Controllers;

use App\Models\PedidoOnline;
use App\Models\Tienda;
use App\Support\OnlineOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

final class PublicOrderTrackingController extends Controller
{
    public function __invoke(Request $request, string $slug, string $operation)
    {
        $expires = $request->integer('expires');
        abort_unless($expires > now()->timestamp && $expires <= now()->addDays(31)->timestamp, 403);

        $store = Tienda::query()->where('slug', $slug)->firstOrFail();
        $order = PedidoOnline::withoutGlobalScopes()
            ->where('tienda_id', $store->id)
            ->where('operacion_id', $operation)
            ->with(['detalles.producto.imagenes', 'venta'])
            ->firstOrFail();

        if (! $order->seguimientoClienteDisponible()) {
            return redirect()
                ->route('storefront.show', ['slug' => $store->slug])
                ->with('order_access_closed', 'El seguimiento de este pedido ha finalizado y ya no se encuentra disponible.');
        }

        $ratingUrl = URL::temporarySignedRoute('storefront.orders.rate', now()->addDays(30), [
            'slug' => $store->slug,
            'operation' => $order->operacion_id,
        ]);

        return view('storefront.order-tracking-modern', compact('store', 'order', 'ratingUrl'));
    }

    public function rate(Request $request, string $slug, string $operation)
    {
        $expires = $request->integer('expires');
        abort_unless($expires > now()->timestamp && $expires <= now()->addDays(31)->timestamp, 403);

        $data = $request->validate([
            'calificacion' => ['required', 'integer', 'between:1,5'],
            'comentario' => ['nullable', 'string', 'max:500'],
        ], [
            'calificacion.required' => 'Selecciona una calificación del 1 al 5.',
            'calificacion.between' => 'La calificación debe estar entre 1 y 5.',
            'comentario.max' => 'El comentario no puede superar los 500 caracteres.',
        ]);

        $store = Tienda::query()->where('slug', $slug)->firstOrFail();
        DB::transaction(function () use ($store, $operation, $data): void {
            $order = PedidoOnline::withoutGlobalScopes()
                ->where('tienda_id', $store->id)
                ->where('operacion_id', $operation)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->estado !== OnlineOrderStatus::DELIVERED) {
                throw ValidationException::withMessages(['calificacion' => 'Solo puedes calificar un pedido entregado.']);
            }
            if (! $order->seguimientoClienteDisponible()) {
                throw ValidationException::withMessages(['calificacion' => 'El plazo de 24 horas para calificar este pedido finalizó o la calificación ya fue registrada.']);
            }

            $order->forceFill([
                'calificacion' => (int) $data['calificacion'],
                'comentario_calificacion' => filled($data['comentario'] ?? null) ? trim($data['comentario']) : null,
                'calificado_at' => now(),
            ])->save();
        }, 3);

        return redirect()
            ->route('storefront.show', ['slug' => $store->slug])
            ->with('purchase_thanks', '¡Gracias por tu compra y por compartir tu experiencia! Tu pedido ha finalizado correctamente y tu opinión nos ayuda a mejorar.');
    }

    public function shippingGuide(Request $request, string $slug, string $operation)
    {
        $expires = $request->integer('expires');
        abort_unless($expires > now()->timestamp && $expires <= now()->addDays(31)->timestamp, 403);

        $store = Tienda::query()->where('slug', $slug)->firstOrFail();
        $order = PedidoOnline::withoutGlobalScopes()
            ->where('tienda_id', $store->id)
            ->where('operacion_id', $operation)
            ->firstOrFail();
        abort_unless($order->guia_envio_path && Storage::disk('local')->exists($order->guia_envio_path), 404);

        $filename = 'guia-'.$order->numero.'.'.pathinfo($order->guia_envio_path, PATHINFO_EXTENSION);
        if ($request->boolean('download')) {
            return Storage::disk('local')->download($order->guia_envio_path, $filename, [
                'Cache-Control' => 'private, no-store, max-age=0',
            ]);
        }

        return Storage::disk('local')->response($order->guia_envio_path, $filename, [
            'Cache-Control' => 'private, no-store, max-age=0',
        ], 'inline');
    }

    public function saleReceipt(Request $request, string $slug, string $operation)
    {
        $expires = $request->integer('expires');
        abort_unless($expires > now()->timestamp && $expires <= now()->addDays(31)->timestamp, 403);

        $store = Tienda::query()->where('slug', $slug)->firstOrFail();
        $order = PedidoOnline::withoutGlobalScopes()
            ->where('tienda_id', $store->id)
            ->where('operacion_id', $operation)
            ->whereNotNull('comprobante_publicado_at')
            ->with(['venta.tienda', 'venta.cliente', 'venta.vendedor', 'venta.detalles.producto', 'venta.pagos'])
            ->firstOrFail();
        abort_unless($order->venta, 404);

        return response(view('storefront.sale-receipt', [
            'venta' => $order->venta,
            'store' => $store,
        ])->render())->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "frame-ancestors 'self'",
        ]);
    }
}
