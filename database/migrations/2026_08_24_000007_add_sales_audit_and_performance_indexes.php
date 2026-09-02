<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('anulada_por')->nullable()->after('estado')->constrained('users')->nullOnDelete();
            $table->timestamp('anulada_at')->nullable()->after('anulada_por');
            $table->string('motivo_anulacion', 500)->nullable()->after('anulada_at');
        });

        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 60);
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');
            $table->string('descripcion', 500);
            $table->json('metadata')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
            $table->index(['tienda_id', 'auditable_type', 'auditable_id'], 'auditoria_entidad_tienda');
            $table->index(['tienda_id', 'accion', 'created_at'], 'auditoria_accion_fecha');
        });

        Schema::table('clientes', fn (Blueprint $table) => $table->index(['tienda_id', 'activo', 'nombre'], 'clientes_busqueda_pos'));
        Schema::table('productos', fn (Blueprint $table) => $table->index(['tienda_id', 'activo', 'nombre'], 'productos_busqueda_pos'));
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->index(['tienda_id', 'codigo_barra'], 'variantes_codigo_barra_tienda');
            $table->index(['tienda_id', 'stock'], 'variantes_stock_tienda');
        });
        Schema::table('caja', fn (Blueprint $table) => $table->index(['tienda_id', 'estado', 'apertura'], 'caja_estado_tienda'));
    }

    public function down(): void
    {
        Schema::table('clientes', fn (Blueprint $table) => $table->dropIndex('clientes_busqueda_pos'));
        Schema::table('productos', fn (Blueprint $table) => $table->dropIndex('productos_busqueda_pos'));
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->dropIndex('variantes_codigo_barra_tienda');
            $table->dropIndex('variantes_stock_tienda');
        });
        Schema::table('caja', fn (Blueprint $table) => $table->dropIndex('caja_estado_tienda'));
        Schema::dropIfExists('auditorias');
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anulada_por');
            $table->dropColumn(['anulada_at', 'motivo_anulacion']);
        });
    }
};
