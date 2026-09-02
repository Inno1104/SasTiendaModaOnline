<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendas', function (Blueprint $table) {
            $table->boolean('precios_incluyen_igv')->default(false)->after('igv');
        });
    }

    public function down(): void
    {
        Schema::table('tiendas', function (Blueprint $table) {
            $table->dropColumn('precios_incluyen_igv');
        });
    }
};
