<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendas', function (Blueprint $table): void {
            $table->unsignedBigInteger('configuracion_version')->default(0)->after('serie_ticket');
        });
    }

    public function down(): void
    {
        Schema::table('tiendas', function (Blueprint $table): void {
            $table->dropColumn('configuracion_version');
        });
    }
};
