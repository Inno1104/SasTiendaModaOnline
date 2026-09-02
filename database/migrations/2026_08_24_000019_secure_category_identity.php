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
        $categories = DB::table('categorias')->select('id', 'tienda_id', 'nombre')->orderBy('id')->get();

        if ($categories->contains(fn ($category) => $category->tienda_id === null)) {
            throw new RuntimeException(
                'Existen categorías sin tienda. Asígnelas antes de ejecutar esta migración.'
            );
        }

        $duplicates = $categories
            ->groupBy(fn ($category) => $category->tienda_id.'|'.Str::lower(Str::squish($category->nombre)))
            ->first(fn ($group) => $group->count() > 1);

        if ($duplicates) {
            throw new RuntimeException(
                'Existen categorías con nombres equivalentes dentro de una tienda. Corríjalas antes de ejecutar esta migración.'
            );
        }

        Schema::table('categorias', function (Blueprint $table) {
            $table->string('nombre_normalizado', 100)->nullable()->after('nombre');
        });

        foreach ($categories as $category) {
            DB::table('categorias')->where('id', $category->id)->update([
                'nombre_normalizado' => Str::lower(Str::squish($category->nombre)),
            ]);
        }

        Schema::table('categorias', function (Blueprint $table) {
            $table->dropUnique('categorias_slug_unique');
            $table->unique(['tienda_id', 'nombre_normalizado'], 'categorias_tienda_nombre_unique');
            $table->unique(['tienda_id', 'slug'], 'categorias_tienda_slug_unique');
        });
    }

    public function down(): void
    {
        $duplicateSlug = DB::table('categorias')
            ->select('slug')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateSlug) {
            throw new RuntimeException(
                'No se puede revertir: existen slugs repetidos entre tiendas.'
            );
        }

        Schema::table('categorias', function (Blueprint $table) {
            $table->dropUnique('categorias_tienda_nombre_unique');
            $table->dropUnique('categorias_tienda_slug_unique');
            $table->unique('slug', 'categorias_slug_unique');
            $table->dropColumn('nombre_normalizado');
        });
    }
};
