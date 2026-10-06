<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendas', function (Blueprint $table) {
            $table->boolean('transferencia_activa')->default(false);
            $table->string('transferencia_banco', 100)->nullable();
            $table->string('transferencia_tipo_cuenta', 30)->nullable();
            $table->string('transferencia_numero_cuenta', 60)->nullable();
            $table->string('transferencia_titular', 150)->nullable();
            $table->string('transferencia_identificacion', 30)->nullable();
            $table->string('transferencia_instrucciones', 500)->nullable();
        });
        Schema::table('pedidos_online', function (Blueprint $table) {
            $table->string('metodo_pago', 30)->default('transferencia')->after('estado');
            $table->string('referencia_pago', 100)->nullable();
            $table->string('comprobante_pago_path', 255)->nullable();
            $table->timestamp('pago_reportado_at')->nullable();
            $table->foreignId('pago_verificado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pago_verificado_at')->nullable();
            $table->index(['tienda_id', 'metodo_pago', 'pago_reportado_at'], 'pedido_online_pago_reportado');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_online', function (Blueprint $table) {
            $table->dropIndex('pedido_online_pago_reportado');
            $table->dropConstrainedForeignId('pago_verificado_por');
            $table->dropColumn(['metodo_pago','referencia_pago','comprobante_pago_path','pago_reportado_at','pago_verificado_at']);
        });
        Schema::table('tiendas', function (Blueprint $table) {
            $table->dropColumn(['transferencia_activa','transferencia_banco','transferencia_tipo_cuenta','transferencia_numero_cuenta','transferencia_titular','transferencia_identificacion','transferencia_instrucciones']);
        });
    }
};
