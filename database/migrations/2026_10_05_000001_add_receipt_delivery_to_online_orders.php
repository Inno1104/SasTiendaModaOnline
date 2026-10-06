<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_online', function (Blueprint $table) {
            $table->timestamp('comprobante_enviado_at')->nullable()->after('venta_id');
            $table->foreignId('comprobante_enviado_por')->nullable()->after('comprobante_enviado_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_online', function (Blueprint $table) {
            $table->dropConstrainedForeignId('comprobante_enviado_por');
            $table->dropColumn('comprobante_enviado_at');
        });
    }
};
