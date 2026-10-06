<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropConstraint();
        $fullName = DB::getDriverName() === 'mysql'
            ? "TRIM(CONCAT(cliente_nombre, ' ', cliente_apellido))"
            : "TRIM(cliente_nombre || ' ' || cliente_apellido)";
        DB::table('pedidos_online')->whereNull('tipo_identificacion')->update([
            'tipo_identificacion' => 'cedula_dni',
            'identificacion' => DB::raw('cliente_dni'),
            'razon_social' => DB::raw($fullName),
        ]);
        DB::table('pedidos_online')->where('requiere_factura', true)->update(['tipo_comprobante' => 'factura']);
        DB::table('pedidos_online')->where('requiere_factura', false)->whereNotIn('tipo_comprobante', ['ticket', 'boleta'])->update(['tipo_comprobante' => 'boleta']);
        $this->addConstraint(
            "tipo_comprobante IN ('ticket','boleta','factura') AND requiere_factura = CASE WHEN tipo_comprobante = 'factura' THEN 1 ELSE 0 END AND ((tipo_identificacion IS NULL AND identificacion IS NULL AND razon_social IS NULL) OR (tipo_identificacion IS NOT NULL AND identificacion IS NOT NULL AND razon_social IS NOT NULL))"
        );
    }

    public function down(): void
    {
        $this->dropConstraint();
        DB::table('pedidos_online')->where('requiere_factura', false)->update([
            'tipo_identificacion' => null,
            'identificacion' => null,
            'razon_social' => null,
        ]);
        $this->addConstraint(
            '((requiere_factura = 0 AND tipo_identificacion IS NULL AND identificacion IS NULL AND razon_social IS NULL) OR (requiere_factura = 1 AND tipo_identificacion IS NOT NULL AND identificacion IS NOT NULL AND razon_social IS NOT NULL))'
        );
    }

    private function dropConstraint(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $exists = DB::table('information_schema.table_constraints')
                ->where('constraint_schema', DB::getDatabaseName())
                ->where('table_name', 'pedidos_online')
                ->where('constraint_name', 'pedido_online_fiscal_valido')
                ->exists();
            if ($exists) {
                DB::statement('ALTER TABLE pedidos_online DROP CHECK pedido_online_fiscal_valido');
            }
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE pedidos_online DROP CONSTRAINT IF EXISTS pedido_online_fiscal_valido');
        } elseif ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS pedidos_online_integridad_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS pedidos_online_integridad_update');
        }
    }

    private function addConstraint(string $fiscal): void
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'pgsql'], true)) {
            DB::statement("ALTER TABLE pedidos_online ADD CONSTRAINT pedido_online_fiscal_valido CHECK ({$fiscal})");
        } elseif ($driver === 'sqlite') {
            $integrity = "NEW.estado IN ('pendiente','esperando_pago','pagado','preparando','enviado','entregado','cancelado')"
                .' AND NEW.subtotal >= 0 AND NEW.descuento >= 0 AND NEW.envio >= 0 AND NEW.total >= 0'
                .' AND NEW.total = NEW.subtotal - NEW.descuento + NEW.envio'
                .' AND '.preg_replace('/\b(tipo_comprobante|requiere_factura|tipo_identificacion|identificacion|razon_social)\b/', 'NEW.$1', $fiscal);
            DB::unprepared("CREATE TRIGGER pedidos_online_integridad_insert BEFORE INSERT ON pedidos_online WHEN NOT ({$integrity}) BEGIN SELECT RAISE(ABORT, 'Registro de pedido online inconsistente'); END");
            DB::unprepared("CREATE TRIGGER pedidos_online_integridad_update BEFORE UPDATE ON pedidos_online WHEN NOT ({$integrity}) BEGIN SELECT RAISE(ABORT, 'Registro de pedido online inconsistente'); END");
        }
    }
};
