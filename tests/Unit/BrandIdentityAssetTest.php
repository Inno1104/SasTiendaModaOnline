<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BrandIdentityAssetTest extends TestCase
{
    public function test_identity_writes_are_serialized_and_database_constraint_is_declared(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $manager = file_get_contents($projectRoot.'/app/Services/BrandManager.php');
        $migration = file_get_contents($projectRoot.'/database/migrations/2026_08_24_000022_secure_brand_identity.php');

        $this->assertStringContainsString('lockForUpdate()', $manager);
        $this->assertStringContainsString("unique(['tienda_id', 'nombre_normalizado']", $migration);
        $this->assertStringContainsString('marcas sin tienda', $migration);
        $this->assertStringContainsString('nombres equivalentes', $migration);
    }
}
