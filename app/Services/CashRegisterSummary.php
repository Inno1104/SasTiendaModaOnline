<?php

namespace App\Services;

use App\Models\Caja;
use App\Support\Money;
use App\Support\PaymentMethods;
use App\Support\SaleStatus;
use Illuminate\Support\Facades\DB;

class CashRegisterSummary
{
    public function calculate(Caja $register): array
    {
        $sales = $register->ventas()
            ->whereIn('estado', [SaleStatus::COMPLETED, SaleStatus::RETURNED])
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(total), 0) as sales_total')
            ->selectRaw('COALESCE(SUM(vuelto), 0) as change_total')
            ->first();
        $salesTotalCents = Money::toCents($sales->sales_total);
        $salesCount = (int) $sales->sales_count;
        $changeCents = Money::toCents($sales->change_total);

        $paymentTotals = DB::table('pagos_venta')
            ->join('ventas', 'pagos_venta.venta_id', '=', 'ventas.id')
            ->where('ventas.tienda_id', $register->tienda_id)
            ->where('ventas.caja_id', $register->id)
            ->whereIn('ventas.estado', [SaleStatus::COMPLETED, SaleStatus::RETURNED])
            ->selectRaw('pagos_venta.metodo, SUM(pagos_venta.monto) as total')
            ->groupBy('pagos_venta.metodo')
            ->pluck('total', 'metodo');

        $paymentCents = collect(PaymentMethods::ALL)->mapWithKeys(
            fn (string $method) => [$method => Money::toCents($paymentTotals[$method] ?? 0)]
        );
        $payments = $paymentCents->map(
            fn (int $amount) => Money::fromCents($amount)
        )->all();
        $movements = $register->movimientos()
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) as income_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END), 0) as expense_total")
            ->first();
        $incomeCents = Money::toCents($movements->income_total);
        $expenseCents = Money::toCents($movements->expense_total);
        $expectedCents = Money::toCents($register->monto_inicial)
            + $paymentCents[PaymentMethods::CASH]
            + $incomeCents
            - $changeCents
            - $expenseCents;

        return [
            'ventas_cantidad' => $salesCount,
            'total_ventas' => Money::fromCents($salesTotalCents),
            'pagos' => $payments,
            'efectivo_recibido' => $payments[PaymentMethods::CASH],
            'vuelto' => Money::fromCents($changeCents),
            'total_ingresos' => Money::fromCents($incomeCents),
            'total_egresos' => Money::fromCents($expenseCents),
            'efectivo_esperado' => Money::fromCents($expectedCents),
        ];
    }
}
