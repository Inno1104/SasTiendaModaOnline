<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_online', function (Blueprint $table) {
            $table->foreignId('venta_id')->nullable()->after('pago_verificado_at')
                ->unique()
                ->constrained('ventas')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_online', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venta_id');
        });
    }
};
