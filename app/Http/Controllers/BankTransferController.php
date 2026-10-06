<?php

namespace App\Http\Controllers;

use App\Models\PedidoOnline;
use App\Models\StockReserva;
use App\Models\Tienda;
use App\Support\OnlineOrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class BankTransferController extends Controller
{
    public function show(string $slug, string $operation)
    {
        [$store, $order] = $this->context($slug, $operation);

        return view('storefront.transfer-payment', compact('store', 'order'));
    }

    public function store(Request $request, string $slug, string $operation): RedirectResponse
    {
        [$store, $order] = $this->context($slug, $operation);
        abort_unless($store->transferencia_activa, 409);
        abort_unless(in_array($order->estado, [OnlineOrderStatus::PENDING, OnlineOrderStatus::AWAITING_PAYMENT], true), 409);

        $data = $request->validate([
            'referencia_pago' => ['required', 'string', 'min:4', 'max:100'],
            'comprobante' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('comprobante')->store('transferencias/'.$store->id, 'local');
        $previousPath = null;

        try {
            DB::transaction(function () use ($order, $data, $path, &$previousPath): void {
                $lockedOrder = PedidoOnline::withoutGlobalScopes()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                abort_unless(in_array($lockedOrder->estado, [OnlineOrderStatus::PENDING, OnlineOrderStatus::AWAITING_PAYMENT], true), 409);
                $previousPath = $lockedOrder->comprobante_pago_path;

                $reservations = StockReserva::withoutGlobalScopes()
                    ->where('pedido_online_id', $lockedOrder->id)
                    ->where('estado', StockReserva::ACTIVE)
                    ->where('expira_at', '>', now())
                    ->lockForUpdate()
                    ->get();
                if ($reservations->isEmpty()) {
                    throw ValidationException::withMessages([
                        'comprobante' => 'La reserva de inventario venció. Vuelve al catálogo para realizar el pedido nuevamente.',
                    ]);
                }

                $reportedAt = now();
                $expires = $reportedAt->copy()->addHours(24);
                $lockedOrder->forceFill([
                    'estado' => OnlineOrderStatus::AWAITING_PAYMENT,
                    'referencia_pago' => trim($data['referencia_pago']),
                    'comprobante_pago_path' => $path,
                    'pago_reportado_at' => $reportedAt,
                    'reserva_expira_at' => $expires,
                ])->save();
                StockReserva::withoutGlobalScopes()
                    ->whereKey($reservations->modelKeys())
                    ->update(['expira_at' => $expires, 'updated_at' => $reportedAt]);
            }, 3);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        if ($previousPath && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        return back()->with('transfer_success', 'Comprobante enviado. La tienda verificará la transferencia.');
    }

    private function context(string $slug, string $operation): array
    {
        $store = Tienda::query()->where('slug', $slug)->firstOrFail();
        abort_unless($store->estaPublicada(), 404);
        $order = PedidoOnline::withoutGlobalScopes()->where('tienda_id', $store->id)->where('operacion_id', $operation)->firstOrFail();

        return [$store, $order];
    }
}
