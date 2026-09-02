<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendas', function (Blueprint $table) {
            $table->boolean('publicada')->default(false)->index()->after('redes_sociales');
            $table->string('descripcion_publica', 500)->nullable()->after('publicada');
            $table->string('color_primario', 7)->default('#E8398C')->after('descripcion_publica');
            $table->json('horarios')->nullable()->after('color_primario');
            $table->json('condiciones_venta')->nullable()->after('horarios');
        });
    }

    public function down(): void
    {
        Schema::table('tiendas', function (Blueprint $table) {
            $table->dropIndex(['publicada']);
            $table->dropColumn(['publicada', 'descripcion_publica', 'color_primario', 'horarios', 'condiciones_venta']);
        });
    }
};
