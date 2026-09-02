<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(
                ['tienda_id', 'es_super_admin', 'rol_id', 'activo', 'name', 'id'],
                'usuarios_tienda_rol_estado_nombre'
            );
            $table->index(
                ['tienda_id', 'es_super_admin', 'created_at', 'id'],
                'usuarios_tienda_fecha'
            );
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('usuarios_tienda_rol_estado_nombre');
            $table->dropIndex('usuarios_tienda_fecha');
        });
    }
};
