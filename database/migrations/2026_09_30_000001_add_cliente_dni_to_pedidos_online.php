<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_online', function (Blueprint $table) {
            $table->string('cliente_dni', 30)->nullable()->after('cliente_apellido');
            $table->index(['tienda_id', 'cliente_dni'], 'pedido_online_cliente_dni');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_online', function (Blueprint $table) {
            $table->dropIndex('pedido_online_cliente_dni');
            $table->dropColumn('cliente_dni');
        });
    }
};
