<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_online', function (Blueprint $table) {
            $table->timestamp('comprobante_publicado_at')->nullable()->after('comprobante_enviado_por');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_online', fn (Blueprint $table) => $table->dropColumn('comprobante_publicado_at'));
    }
};
