<?php

namespace Tests\Unit;

use Tests\TestCase;

class StockExperienceAssetTest extends TestCase
{
    public function test_stock_forms_prevent_duplicate_submissions_and_expose_busy_state(): void
    {
        $script = file_get_contents(public_path('js/stock.js'));

        $this->assertStringContainsString("form.dataset.submitting === 'true'", $script);
        $this->assertStringContainsString("form.dataset.submitting = 'true'", $script);
        $this->assertStringContainsString("form.setAttribute('aria-busy', 'true')", $script);
        $this->assertStringContainsString('button.disabled = true', $script);
        $this->assertStringContainsString('window.confirm', $script);
    }

    public function test_production_checklist_covers_mysql_backup_restore_and_recovery(): void
    {
        $checklist = file_get_contents(base_path('docs/stock-production-checklist.md'));

        $this->assertStringContainsString('MySqlInventoryConcurrencyTest.php', $checklist);
        $this->assertStringContainsString('mysqldump --single-transaction', $checklist);
        $this->assertStringContainsString('mysql BASE_DATOS_RESTAURACION', $checklist);
        $this->assertStringContainsString('SHA-256', $checklist);
        $this->assertStringContainsString('último saldo del kardex', $checklist);
    }
}
