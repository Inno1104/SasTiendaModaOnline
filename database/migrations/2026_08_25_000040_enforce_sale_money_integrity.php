<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $checks = [
        'ventas_importes_validos' => 'subtotal >= 0 AND descuento >= 0 AND igv >= 0 AND total >= 0 AND monto_pagado >= 0 AND vuelto >= 0',
        'detalle_ventas_importes_validos' => 'cantidad > 0 AND precio_unitario >= 0 AND descuento >= 0 AND subtotal >= 0',
        'pagos_venta_monto_valido' => 'monto > 0',
    ];

    public function up(): void
    {
        $this->assertExistingDataIsValid();

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE ventas ADD CONSTRAINT ventas_importes_validos CHECK ('.$this->checks['ventas_importes_validos'].')');
        DB::statement('ALTER TABLE detalle_ventas ADD CONSTRAINT detalle_ventas_importes_validos CHECK ('.$this->checks['detalle_ventas_importes_validos'].')');
        DB::statement('ALTER TABLE pagos_venta ADD CONSTRAINT pagos_venta_monto_valido CHECK ('.$this->checks['pagos_venta_monto_valido'].')');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE pagos_venta DROP CHECK pagos_venta_monto_valido');
        DB::statement('ALTER TABLE detalle_ventas DROP CHECK detalle_ventas_importes_validos');
        DB::statement('ALTER TABLE ventas DROP CHECK ventas_importes_validos');
    }

    private function assertExistingDataIsValid(): void
    {
        $invalidSales = DB::table('ventas')
            ->whereRaw('NOT ('.$this->checks['ventas_importes_validos'].')')
            ->exists();
        $invalidDetails = DB::table('detalle_ventas')
            ->whereRaw('NOT ('.$this->checks['detalle_ventas_importes_validos'].')')
            ->exists();
        $invalidPayments = DB::table('pagos_venta')
            ->whereRaw('NOT ('.$this->checks['pagos_venta_monto_valido'].')')
            ->exists();

        if ($invalidSales || $invalidDetails || $invalidPayments) {
            throw new RuntimeException(
                'Existen importes de ventas inválidos. Corrija los datos antes de aplicar las restricciones.'
            );
        }
    }
};
