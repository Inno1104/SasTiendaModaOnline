<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $subcategories = DB::table('subcategorias')->select('id', 'categoria_id')->orderBy('id')->get();

        foreach ($subcategories as $subcategory) {
            if (! DB::table('categorias')->where('id', $subcategory->categoria_id)->exists()) {
                throw new RuntimeException(
                    "La subcategoría {$subcategory->id} no tiene una categoría válida. Corríjala antes de migrar."
                );
            }
        }

        Schema::table('subcategorias', function (Blueprint $table) {
            $table->unsignedBigInteger('tienda_id')->nullable()->after('id');
        });

        foreach ($subcategories as $subcategory) {
            $storeId = DB::table('categorias')->where('id', $subcategory->categoria_id)->value('tienda_id');

            if ($storeId === null) {
                throw new RuntimeException(
                    "La categoría de la subcategoría {$subcategory->id} no tiene tienda. Corríjala antes de migrar."
                );
            }

            DB::table('subcategorias')->where('id', $subcategory->id)->update(['tienda_id' => $storeId]);
        }

        Schema::table('subcategorias', function (Blueprint $table) {
            $table->dropUnique('subcategorias_slug_unique');
            $table->index('tienda_id', 'subcategorias_tienda_index');
            $table->unique(['tienda_id', 'slug'], 'subcategorias_tienda_slug_unique');
        });
    }

    public function down(): void
    {
        $duplicateSlug = DB::table('subcategorias')
            ->select('slug')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateSlug) {
            throw new RuntimeException('No se puede revertir: existen slugs de subcategoría repetidos entre tiendas.');
        }

        Schema::table('subcategorias', function (Blueprint $table) {
            $table->dropUnique('subcategorias_tienda_slug_unique');
            $table->dropIndex('subcategorias_tienda_index');
            $table->unique('slug', 'subcategorias_slug_unique');
            $table->dropColumn('tienda_id');
        });
    }
};
