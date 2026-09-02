<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CashRegisterAssetTest extends TestCase
{
    public function test_cash_register_views_use_shared_assets_without_inline_styles_or_handlers(): void
    {
        $index = file_get_contents(__DIR__.'/../../resources/views/caja/index.blade.php');
        $detail = file_get_contents(__DIR__.'/../../resources/views/caja/show.blade.php');
        $movements = file_get_contents(__DIR__.'/../../resources/views/caja/partials/movements.blade.php');

        foreach ([$index, $detail] as $view) {
            $this->assertStringContainsString("asset('css/caja.css')", $view);
            $this->assertStringContainsString("asset('js/caja.js')", $view);
            $this->assertStringNotContainsString('style=', $view);
            $this->assertStringNotContainsString('onclick=', $view);
            $this->assertStringNotContainsString('onsubmit=', $view);
        }

        $this->assertStringContainsString("@include('caja.partials.movements'", $index);
        $this->assertStringContainsString('cash-operations-grid', $movements);
        $this->assertStringContainsString('cash-money-input', $movements);
        $this->assertStringNotContainsString('style=', $movements);
        $this->assertStringNotContainsString('onclick=', $movements);
    }

    public function test_cash_register_styles_follow_theme_and_define_responsive_accessible_states(): void
    {
        $css = file_get_contents(__DIR__.'/../../public/css/caja.css');

        $this->assertStringContainsString('var(--accent)', $css);
        $this->assertStringContainsString('.cash-live-status', $css);
        $this->assertStringContainsString('.cash-empty-state', $css);
        $this->assertStringContainsString('.cash-summary-icon', $css);
        $this->assertStringContainsString('.cash-operations-grid', $css);
        $this->assertStringContainsString('.cash-close-dialog::backdrop', $css);
        $this->assertStringContainsString('@media (max-width: 600px)', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertStringContainsString('@media print', $css);
    }

    public function test_cash_register_script_keeps_critical_interaction_guards(): void
    {
        $script = file_get_contents(__DIR__.'/../../public/js/caja.js');

        $this->assertStringContainsString("document.querySelectorAll('[data-cash-form]')", $script);
        $this->assertStringContainsString('button.disabled = true', $script);
        $this->assertStringContainsString('dialog.showModal()', $script);
        $this->assertStringContainsString('notesInput.required = difference !== 0', $script);
        $this->assertStringContainsString('window.print()', $script);
    }

    public function test_cash_register_performance_migration_defines_filter_and_aggregate_indexes(): void
    {
        $migration = file_get_contents(__DIR__.'/../../database/migrations/2026_08_24_000012_optimize_cash_register_queries.php');

        $this->assertStringContainsString('caja_historial_fecha_estado', $migration);
        $this->assertStringContainsString('caja_historial_responsable_fecha', $migration);
        $this->assertStringContainsString('caja_historial_conciliacion_fecha', $migration);
        $this->assertStringContainsString('ventas_caja_estado', $migration);
        $this->assertStringContainsString('movimientos_caja_tipo', $migration);
    }
}
