<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->foreignId('anulada_por')->nullable()->after('estado')->constrained('users')->nullOnDelete();
            $table->timestamp('anulada_at')->nullable()->after('anulada_por');
            $table->string('motivo_anulacion', 500)->nullable()->after('anulada_at');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anulada_por');
            $table->dropColumn(['anulada_at', 'motivo_anulacion']);
        });
    }
};
