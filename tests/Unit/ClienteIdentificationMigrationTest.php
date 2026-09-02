<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ClienteIdentificationMigrationTest extends TestCase
{
    public function test_migration_defines_store_scoped_identity_constraints_and_counter(): void
    {
        $migration = file_get_contents(__DIR__.'/../../database/migrations/2026_08_24_000013_secure_customer_identification.php');

        $this->assertStringContainsString('clientes_tienda_codigo_unique', $migration);
        $this->assertStringContainsString('clientes_tienda_dni_unique', $migration);
        $this->assertStringContainsString('clientes_tienda_email_unique', $migration);
        $this->assertStringContainsString("Schema::create('cliente_contadores'", $migration);
        $this->assertStringContainsString("foreignId('tienda_id')->unique()", $migration);
    }

    public function test_creator_locks_and_updates_the_store_counter_transactionally(): void
    {
        $creator = file_get_contents(__DIR__.'/../../app/Services/ClienteCreator.php');

        $this->assertStringContainsString('DB::transaction', $creator);
        $this->assertStringContainsString('insertOrIgnore', $creator);
        $this->assertStringContainsString('lockForUpdate()', $creator);
        $this->assertStringContainsString("'tienda_id' => \$tiendaId", $creator);
    }
}
