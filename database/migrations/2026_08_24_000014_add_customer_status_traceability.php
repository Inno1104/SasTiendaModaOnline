<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('estado_cambiado_por')->nullable()->after('activo')->constrained('users')->nullOnDelete();
            $table->timestamp('estado_cambiado_at')->nullable()->after('estado_cambiado_por');
            $table->string('motivo_estado', 500)->nullable()->after('estado_cambiado_at');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('estado_cambiado_por');
            $table->dropColumn(['estado_cambiado_at', 'motivo_estado']);
        });
    }
};
