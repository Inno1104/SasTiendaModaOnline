<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendas', function (Blueprint $table) {
            $table->string('sitio_web', 2048)->nullable()->after('ciudad');
            $table->json('redes_sociales')->nullable()->after('sitio_web');
        });
    }

    public function down(): void
    {
        Schema::table('tiendas', function (Blueprint $table) {
            $table->dropColumn(['sitio_web', 'redes_sociales']);
        });
    }
};
