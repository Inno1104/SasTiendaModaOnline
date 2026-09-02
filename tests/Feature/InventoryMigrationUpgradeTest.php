<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryMigrationUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_stock_count_is_preserved_and_backfilled_by_hardening_migration(): void
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create(['nombre' => 'Tienda existente', 'slug' => 'existente-'.$suffix, 'estado' => 'activa']);
        $user = User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => 'Administrador'])->id,
            'tienda_id' => $store->id,
            'name' => 'Responsable histórico',
            'email' => $suffix.'@inventory-upgrade.test',
            'password' => 'password',
            'activo' => true,
        ]);
        $category = Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría histórica',
            'nombre_normalizado' => 'categoria historica',
            'slug' => 'categoria-historica-'.$suffix,
            'genero' => 'unisex',
            'activo' => true,
        ]);
        $product = Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => 'LEGACY-'.Str::upper($suffix),
            'nombre' => 'Producto histórico',
            'genero' => 'unisex',
            'tipo' => 'casual',
            'precio_compra' => 5,
            'precio_venta' => 10,
            'activo' => true,
        ]);
        $variant = ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Negro',
            'stock' => 10,
            'stock_minimo' => 2,
        ]);
        Schema::drop('stock_conteos');
        $legacyMigration = require database_path('migrations/2026_08_25_000028_create_stock_counts.php');
        $legacyMigration->up();

        $countId = DB::table('stock_conteos')->insertGetId([
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'variante_id' => $variant->id,
            'contado_por' => $user->id,
            'stock_sistema' => 10,
            'stock_fisico' => 8,
            'diferencia' => -2,
            'version_stock' => 0,
            'estado' => 'pendiente',
            'motivo' => 'Conteo anterior a la actualización',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hardeningMigration = require database_path('migrations/2026_08_26_000047_harden_stock_counts.php');
        $hardeningMigration->up();
        $count = DB::table('stock_conteos')->find($countId);

        $this->assertSame('pendiente', $count->estado);
        $this->assertSame(-2, $count->diferencia);
        $this->assertTrue(Str::isUuid($count->operacion_id));
        $this->assertSame(64, strlen($count->operacion_hash));
        $this->assertTrue(Schema::hasColumns('stock_conteos', [
            'operacion_id', 'operacion_hash', 'reemplazado_por_conteo_id',
            'reemplazado_por_usuario_id', 'reemplazado_at',
        ]));
    }
}
