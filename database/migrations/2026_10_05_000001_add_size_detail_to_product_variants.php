<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->string('detalle_talla', 500)->nullable()->after('talla');
        });
    }

    public function down(): void
    {
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->dropColumn('detalle_talla');
        });
    }
};
