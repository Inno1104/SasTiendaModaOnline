<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendas', function (Blueprint $table) {
            $table->string('whatsapp', 16)->nullable()->after('telefono');
            $table->char('pais_codigo', 2)->nullable()->after('direccion');
            $table->string('provincia', 100)->nullable()->after('pais_codigo');
            $table->string('ciudad', 100)->nullable()->after('provincia');
        });
    }

    public function down(): void
    {
        Schema::table('tiendas', function (Blueprint $table) {
            $table->dropColumn(['whatsapp', 'pais_codigo', 'provincia', 'ciudad']);
        });
    }
};
