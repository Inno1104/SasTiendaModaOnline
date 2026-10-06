<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_online', function (Blueprint $table) {
            $table->string('tipo_comprobante', 20)->default('boleta')->after('requiere_factura');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_online', fn (Blueprint $table) => $table->dropColumn('tipo_comprobante'));
    }
};
