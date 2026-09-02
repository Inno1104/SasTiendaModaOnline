<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CategoryIdentityAssetTest extends TestCase
{
    public function test_identity_writes_are_serialized_and_database_constraints_are_declared(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $manager = file_get_contents($projectRoot.'/app/Services/CategoryManager.php');
        $migration = file_get_contents($projectRoot.'/database/migrations/2026_08_24_000019_secure_category_identity.php');

        $this->assertStringContainsString('lockForUpdate()', $manager);
        $this->assertStringContainsString("unique(['tienda_id', 'nombre_normalizado']", $migration);
        $this->assertStringContainsString("unique(['tienda_id', 'slug']", $migration);
        $this->assertStringContainsString('categorías sin tienda', $migration);
    }
}
