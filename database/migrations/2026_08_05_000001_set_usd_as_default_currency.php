<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tiendas')
            ->where('moneda', 'PEN')
            ->orWhere('simbolo_moneda', 'S/')
            ->update(['moneda' => 'USD', 'simbolo_moneda' => '$']);
    }

    public function down(): void
    {
        DB::table('tiendas')
            ->where('moneda', 'USD')
            ->where('simbolo_moneda', '$')
            ->update(['moneda' => 'PEN', 'simbolo_moneda' => 'S/']);
    }
};
