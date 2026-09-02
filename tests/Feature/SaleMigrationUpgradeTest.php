<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SaleMigrationUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_sale_is_preserved_and_backfilled_when_snapshot_migration_is_applied(): void
    {
        $suffix = uniqid();
        $role = Rol::create(['nombre' => 'Administrador migración']);
        $store = Tienda::create([
            'nombre' => 'Tienda existente',
            'slug' => 'tienda-existente-'.$suffix,
            'estado' => 'activa',
            'ruc' => 'RUC-EXISTENTE',
        ]);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Responsable histórico',
            'email' => $suffix.'@migration.test',
            'password' => 'password',
            'activo' => true,
        ]);
        $migration = require database_path('migrations/2026_08_25_000044_add_sale_commercial_snapshot.php');
        $migration->down();

        $saleId = DB::table('ventas')->insertGetId([
            'tienda_id' => $store->id,
            'numero_venta' => 'LEGACY-00000001',
            'user_id' => $user->id,
            'fecha' => '2026-08-20',
            'tipo_comprobante' => 'ticket',
            'serie' => 'LEGACY',
            'correlativo' => '00000001',
            'subtotal' => 10,
            'total' => 10,
            'monto_pagado' => 10,
            'estado' => 'completada',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();
        $sale = DB::table('ventas')->find($saleId);

        $this->assertSame('LEGACY-00000001', $sale->numero_venta);
        $this->assertNotNull($sale->commercial_snapshot);
        $this->assertSame(64, strlen($sale->commercial_snapshot_hash));
        $this->assertTrue(Schema::hasColumns('ventas', ['commercial_snapshot', 'commercial_snapshot_hash']));
    }
}
