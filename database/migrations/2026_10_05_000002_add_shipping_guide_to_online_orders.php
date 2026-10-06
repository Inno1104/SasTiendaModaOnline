<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_online', function (Blueprint $table) {
            $table->string('guia_envio_path', 255)->nullable()->after('enviado_at');
            $table->timestamp('guia_envio_subida_at')->nullable()->after('guia_envio_path');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_online', function (Blueprint $table) {
            $table->dropColumn(['guia_envio_path', 'guia_envio_subida_at']);
        });
    }
};
