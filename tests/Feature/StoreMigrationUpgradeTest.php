<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StoreMigrationUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_store_is_preserved_when_profile_migrations_are_applied(): void
    {
        $identity = require database_path('migrations/2026_08_26_000049_add_store_commercial_identity.php');
        $publicData = require database_path('migrations/2026_08_26_000050_add_store_public_data.php');
        $settingsVersion = require database_path('migrations/2026_08_26_000051_add_store_settings_version.php');

        $settingsVersion->down();
        $publicData->down();
        $identity->down();

        $storeId = DB::table('tiendas')->insertGetId([
            'nombre' => 'Tienda histórica',
            'slug' => 'tienda-historica-produccion',
            'ruc' => '1790012345001',
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $identity->up();
        $publicData->up();
        $settingsVersion->up();

        $store = DB::table('tiendas')->find($storeId);
        $this->assertSame('Tienda histórica', $store->nombre);
        $this->assertSame('1790012345001', $store->ruc);
        $this->assertSame(0, (int) $store->configuracion_version);
        $this->assertNull($store->whatsapp);
        $this->assertNull($store->redes_sociales);
        $this->assertTrue(Schema::hasColumns('tiendas', [
            'whatsapp', 'pais_codigo', 'provincia', 'ciudad', 'sitio_web',
            'redes_sociales', 'configuracion_version',
        ]));
    }
}
