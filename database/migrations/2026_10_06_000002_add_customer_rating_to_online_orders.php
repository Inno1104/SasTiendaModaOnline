<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_online', function (Blueprint $table): void {
            $table->unsignedTinyInteger('calificacion')->nullable()->after('entregado_at');
            $table->string('comentario_calificacion', 500)->nullable()->after('calificacion');
            $table->timestamp('calificado_at')->nullable()->after('comentario_calificacion');
            $table->index(['tienda_id', 'calificacion', 'calificado_at'], 'pedido_online_calificacion');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_online', function (Blueprint $table): void {
            $table->dropIndex('pedido_online_calificacion');
            $table->dropColumn(['calificacion', 'comentario_calificacion', 'calificado_at']);
        });
    }
};
