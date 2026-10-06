<?php

namespace App\Http\Controllers;

use App\Mail\OnlineOrderReceiptMail;
use App\Models\PedidoOnline;
use App\Services\OnlineOrderSaleCreator;
use App\Services\StockReservationManager;
use App\Services\VentaCanceller;
use App\Support\OnlineOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

final class PedidoOnlineController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(OnlineOrderStatus::ALL)],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);
        $storeId = (int) $request->user()->tienda_id;
        $query = PedidoOnline::withoutGlobalScopes()
            ->where('tienda_id', $storeId)
            ->whereNotNull('pago_reportado_at');

        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
            $query->where(function ($orders) use ($pattern) {
                $orders->whereRaw("LOWER(numero) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(cliente_nombre) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(cliente_apellido) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(cliente_dni) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(cliente_email) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(cliente_telefono) LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
        $query->when($filters['estado'] ?? null, fn ($orders, $status) => $orders->where('estado', $status))
            ->when($filters['desde'] ?? null, fn ($orders, $date) => $orders->whereDate('created_at', '>=', $date))
            ->when($filters['hasta'] ?? null, fn ($orders, $date) => $orders->whereDate('created_at', '<=', $date));

        $orders = $query->withCount('detalles')->latest('id')->paginate(15)->withQueryString();
        $summary = PedidoOnline::withoutGlobalScopes()
            ->where('tienda_id', $storeId)
            ->whereNotNull('pago_reportado_at')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as esperando', [OnlineOrderStatus::AWAITING_PAYMENT])
            ->selectRaw('SUM(CASE WHEN estado IN (?, ?) THEN 1 ELSE 0 END) as proceso', [OnlineOrderStatus::PAID, OnlineOrderStatus::PREPARING])
            ->selectRaw('SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as enviados', [OnlineOrderStatus::SHIPPED])
            ->selectRaw('SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as entregados', [OnlineOrderStatus::DELIVERED])
            ->first();

        return view('pedidos-online.index', [
            'orders' => $orders,
            'summary' => $summary,
            'statuses' => array_values(array_diff(OnlineOrderStatus::ALL, [OnlineOrderStatus::PENDING])),
        ]);
    }

    public function show(Request $request, int $pedido)
    {
        $order = PedidoOnline::withoutGlobalScopes()
            ->where('tienda_id', $request->user()->tienda_id)
            ->whereNotNull('pago_reportado_at')
            ->with([
                'detalles.producto.imagenes',
                'detalles.variante',
                'direcciones',
                'venta',
            ])
            ->findOrFail($pedido);

        return view('pedidos-online.detail-modern', compact('order'));
    }

    public function verify(
        Request $request,
        StockReservationManager $reservations,
        OnlineOrderSaleCreator $sales,
        int $pedido,
    ) {
        $data = $request->validate([
            'modo' => ['required', Rule::in(['con_factura', 'sin_factura'])],
        ]);
        $order = PedidoOnline::withoutGlobalScopes()->where('tienda_id', $request->user()->tienda_id)->findOrFail($pedido);
        if ($order->pago_verificado_at && $order->venta_id) {
            return back()->with('success', 'Este pago ya fue verificado. No se generó una venta duplicada.');
        }
        if ($order->estado !== OnlineOrderStatus::AWAITING_PAYMENT || ! $order->pago_reportado_at || ! $order->comprobante_pago_path) {
            return back()->with('error', 'El pedido ya no está disponible para verificar o no tiene un comprobante bancario válido.');
        }

        $result = DB::transaction(function () use ($order, $request, $reservations, $sales) {
            $locked = PedidoOnline::withoutGlobalScopes()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->pago_verificado_at && $locked->venta_id) {
                return ['already_verified' => true, 'sale' => $locked->venta()->firstOrFail()];
            }
            if ($locked->estado !== OnlineOrderStatus::AWAITING_PAYMENT || ! $locked->pago_reportado_at || ! $locked->comprobante_pago_path) {
                throw ValidationException::withMessages(['pago' => 'El pedido ya no está disponible para verificar. Actualiza la pantalla e inténtalo nuevamente.']);
            }
            $sale = $sales->create($locked, $request->user());
            $reservations->consume($locked, $request->user(), $sale);
            $locked->forceFill(['estado' => OnlineOrderStatus::PAID, 'pagado_at' => now(), 'pago_verificado_at' => now(), 'pago_verificado_por' => $request->user()->id])->save();

            return ['already_verified' => false, 'sale' => $sale];
        }, 3);

        if ($result['already_verified']) {
            return back()->with('success', 'Este pago ya fue verificado. No se generó una venta duplicada.');
        }

        $sale = $result['sale'];

        $includeReceipt = $data['modo'] === 'con_factura';
        $order->refresh()->forceFill([
            'comprobante_enviado_por' => $request->user()->id,
            'comprobante_publicado_at' => $includeReceipt ? now() : null,
        ])->save();

        try {
            Mail::to($order->cliente_email)->send(new OnlineOrderReceiptMail($order->load('venta'), $includeReceipt));
            $order->forceFill(['comprobante_enviado_at' => now()])->save();
        } catch (Throwable $exception) {
            report($exception);

            $message = $includeReceipt
                ? 'El pago fue confirmado y la factura ya está disponible para el cliente, pero no se pudo enviar el correo.'
                : 'El pago fue confirmado, pero no se pudo enviar el correo de confirmación.';

            return back()->with('error', $message);
        }

        $message = $includeReceipt
            ? 'Pago confirmado. La factura fue publicada y enviada a '.$order->cliente_email.'.'
            : 'Pago confirmado. Se envió la confirmación sin publicar la factura.';

        return back()->with('success', $message);
    }

    public function receipt(Request $request, int $pedido)
    {
        $order = PedidoOnline::withoutGlobalScopes()
            ->where('tienda_id', $request->user()->tienda_id)
            ->whereNotNull('pago_reportado_at')
            ->findOrFail($pedido);
        abort_unless($order->comprobante_pago_path && Storage::disk('local')->exists($order->comprobante_pago_path), 404);

        $extension = strtolower(pathinfo($order->comprobante_pago_path, PATHINFO_EXTENSION));
        $filename = 'comprobante-'.$order->numero.($extension ? '.'.$extension : '');

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($order->comprobante_pago_path, $filename);
        }

        return Storage::disk('local')->response(
            $order->comprobante_pago_path,
            $filename,
            ['Cache-Control' => 'private, no-store, max-age=0'],
            'inline',
        );
    }

    public function sendReceipt(Request $request, int $pedido)
    {
        $data = $request->validate(['modo' => ['required', Rule::in(['con_factura', 'sin_factura'])]]);
        $order = PedidoOnline::withoutGlobalScopes()
            ->where('tienda_id', $request->user()->tienda_id)
            ->with('venta')
            ->findOrFail($pedido);

        abort_unless($order->pago_verificado_at && $order->venta, 409);

        $includeReceipt = $data['modo'] === 'con_factura';
        $order->forceFill([
            'comprobante_enviado_por' => $request->user()->id,
            'comprobante_publicado_at' => $includeReceipt ? now() : null,
        ])->save();

        try {
            Mail::to($order->cliente_email)->send(new OnlineOrderReceiptMail($order, $includeReceipt));
        } catch (Throwable $exception) {
            report($exception);

            if ($includeReceipt) {
                return back()->with('error', 'La factura ya está disponible en la consulta del cliente, pero no se pudo enviar el correo. Revisa la configuración de correo.');
            }

            return back()->with('error', 'No fue posible enviar la confirmación. Revisa la configuración de correo e inténtalo nuevamente.');
        }
        $order->forceFill([
            'comprobante_enviado_at' => now(),
        ])->save();

        $message = $includeReceipt
            ? 'Confirmación y factura enviadas a '.$order->cliente_email.'. El cliente ya puede verla al consultar su pedido.'
            : 'Confirmación de pago enviada sin adjuntar la factura.';

        return back()->with('success', $message);
    }

    public function cancel(
        Request $request,
        StockReservationManager $reservations,
        VentaCanceller $saleCanceller,
        int $pedido,
    ) {
        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $storeId = (int) $request->user()->tienda_id;

        DB::transaction(function () use ($pedido, $storeId, $data, $reservations, $saleCanceller, $request): void {
            $order = PedidoOnline::withoutGlobalScopes()
                ->where('tienda_id', $storeId)
                ->whereNotNull('pago_reportado_at')
                ->whereKey($pedido)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($order->puedeTransicionarA(OnlineOrderStatus::CANCELLED), 409);

            $cancelledAt = now();
            if ($order->venta_id) {
                $saleCanceller->cancel($order->venta, $request->user(), trim($data['motivo']));
            } else {
                $reservations->cancel($order, $cancelledAt);
            }
            $order->forceFill([
                'estado' => OnlineOrderStatus::CANCELLED,
                'cancelado_at' => $cancelledAt,
                'motivo_cancelacion' => trim($data['motivo']),
            ])->save();
        }, 3);

        return redirect()->route('pedidos-online.show', $pedido)
            ->with('success', 'Pedido cancelado y existencias liberadas correctamente.');
    }

    public function updateStatus(Request $request, int $pedido)
    {
        $data = $request->validate([
            'estado' => ['required', Rule::in([
                OnlineOrderStatus::PREPARING,
                OnlineOrderStatus::SHIPPED,
                OnlineOrderStatus::DELIVERED,
            ])],
            'guia_envio' => ['nullable', 'required_if:estado,'.OnlineOrderStatus::SHIPPED, 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $storeId = (int) $request->user()->tienda_id;
        $guidePath = $request->file('guia_envio')?->store('guias-envio/'.$storeId, 'local');

        try {
            DB::transaction(function () use ($pedido, $storeId, $data, $guidePath): void {
                $order = PedidoOnline::withoutGlobalScopes()
                    ->where('tienda_id', $storeId)
                    ->whereNotNull('pago_reportado_at')
                    ->whereKey($pedido)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($order->puedeTransicionarA($data['estado']), 409);
                $timestampField = match ($data['estado']) {
                    OnlineOrderStatus::PREPARING => 'preparando_at',
                    OnlineOrderStatus::SHIPPED => 'enviado_at',
                    OnlineOrderStatus::DELIVERED => 'entregado_at',
                };
                $changes = ['estado' => $data['estado'], $timestampField => now()];
                if ($data['estado'] === OnlineOrderStatus::SHIPPED) {
                    $changes['guia_envio_path'] = $guidePath;
                    $changes['guia_envio_subida_at'] = now();
                }
                $order->forceFill($changes)->save();
            }, 3);
        } catch (Throwable $exception) {
            if ($guidePath) {
                Storage::disk('local')->delete($guidePath);
            }
            throw $exception;
        }

        return back()->with('success', 'El pedido avanzó a “'.OnlineOrderStatus::label($data['estado']).'”. El cliente ya puede verlo en su seguimiento.');
    }
}
