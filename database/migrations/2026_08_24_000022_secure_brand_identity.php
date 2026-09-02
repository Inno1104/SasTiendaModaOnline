<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $brands = DB::table('marcas')->select('id', 'tienda_id', 'nombre')->orderBy('id')->get();

        if ($brands->contains(fn ($brand) => $brand->tienda_id === null)) {
            throw new RuntimeException(
                'Existen marcas sin tienda. Asígnelas antes de ejecutar esta migración.'
            );
        }

        $duplicates = $brands
            ->groupBy(fn ($brand) => $brand->tienda_id.'|'.Str::lower(Str::squish($brand->nombre)))
            ->first(fn ($group) => $group->count() > 1);

        if ($duplicates) {
            throw new RuntimeException(
                'Existen marcas con nombres equivalentes dentro de una tienda. Corríjalas antes de ejecutar esta migración.'
            );
        }

        Schema::table('marcas', function (Blueprint $table) {
            $table->string('nombre_normalizado', 100)->nullable()->after('nombre');
        });

        foreach ($brands as $brand) {
            DB::table('marcas')->where('id', $brand->id)->update([
                'nombre' => Str::squish($brand->nombre),
                'nombre_normalizado' => Str::lower(Str::squish($brand->nombre)),
            ]);
        }

        Schema::table('marcas', function (Blueprint $table) {
            $table->unique(['tienda_id', 'nombre_normalizado'], 'marcas_tienda_nombre_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE marcas MODIFY nombre_normalizado VARCHAR(100) NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->dropUnique('marcas_tienda_nombre_unique');
            $table->dropColumn('nombre_normalizado');
        });
    }
};
