<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ventas')->orderBy('id')->chunkById(500, function ($sales) {
            $existing = DB::table('pagos_venta')->whereIn('venta_id', $sales->pluck('id'))->pluck('venta_id')->all();
            $rows = $sales->whereNotIn('id', $existing)->map(fn ($sale) => [
                'tienda_id' => $sale->tienda_id,
                'venta_id' => $sale->id,
                'metodo' => $sale->metodo_pago === 'mixto' ? 'efectivo' : $sale->metodo_pago,
                'monto' => $sale->metodo_pago === 'efectivo' ? $sale->monto_pagado : $sale->total,
                'referencia' => null,
                'created_at' => $sale->created_at,
                'updated_at' => $sale->updated_at,
            ])->all();
            if ($rows) DB::table('pagos_venta')->insert($rows);
        });
    }

    public function down(): void
    {
        // Los pagos históricos ya forman parte de la contabilidad y no deben borrarse.
    }
};
