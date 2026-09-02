<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SalesHistoryAssetTest extends TestCase
{
    public function test_history_uses_clean_external_responsive_assets(): void
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/ventas/index.blade.php');
        $styles = file_get_contents(__DIR__.'/../../public/css/sales-history-ui.css');
        $script = file_get_contents(__DIR__.'/../../public/js/sales-history.js');

        $this->assertStringContainsString("asset('css/sales-history-ui.css')", $view);
        $this->assertStringContainsString("asset('js/sales-history.js')", $view);
        $this->assertStringNotContainsString('style=', $view);
        $this->assertStringNotContainsString('onsubmit=', $view);
        $this->assertStringContainsString('data-label=', $view);
        $this->assertStringContainsString('@media(max-width:760px)', $styles);
        $this->assertStringContainsString('.sales-table thead{display:none}', $styles);
        $this->assertStringContainsString('dialog.showModal()', $script);
    }

    public function test_receipt_reuses_a_clean_printable_partial(): void
    {
        $detail = file_get_contents(__DIR__.'/../../resources/views/ventas/show.blade.php');
        $printable = file_get_contents(__DIR__.'/../../resources/views/ventas/receipt.blade.php');
        $partial = file_get_contents(__DIR__.'/../../resources/views/ventas/partials/receipt.blade.php');
        $styles = file_get_contents(__DIR__.'/../../public/css/sale-receipt.css');

        $this->assertStringContainsString("@include('ventas.partials.receipt'", $detail);
        $this->assertStringContainsString("@include('ventas.partials.receipt'", $printable);
        $this->assertStringNotContainsString('style=', $detail);
        $this->assertStringNotContainsString('onclick=', $detail.$printable.$partial);
        $this->assertStringContainsString('@media print', $styles);
        $this->assertStringContainsString('@page{size:A4', $styles);
        $this->assertStringContainsString('.format-ticket .print-sheet{width:80mm}', $styles);
        $this->assertStringContainsString('data-receipt-format="ticket"', $printable);
        $this->assertStringContainsString("asset('css/sale-receipt-theme.css')", $detail);
        $this->assertStringContainsString("asset('css/sale-receipt-theme.css')", $printable);
        $theme = file_get_contents(__DIR__.'/../../public/css/sale-receipt-theme.css');
        $this->assertStringContainsString('--receipt-navy:#500025', $theme);
        $this->assertStringContainsString('--receipt-blue:#c2185b', $theme);
        $this->assertStringContainsString("asset('css/sale-receipt-detail.css')", $detail);
        $receiptScript = file_get_contents(__DIR__.'/../../public/js/sale-receipt.js');
        $this->assertStringContainsString('toggleReceiptFormat', $receiptScript);
        $this->assertStringContainsString("classList.toggle('ticket-preview'", $receiptScript);
    }

    public function test_history_migration_defines_filter_and_sort_indexes(): void
    {
        $migration = file_get_contents(__DIR__.'/../../database/migrations/2026_08_24_000008_optimize_sale_history_queries.php');

        $this->assertStringContainsString('ventas_historial_tienda_fecha', $migration);
        $this->assertStringContainsString('ventas_historial_vendedor_fecha', $migration);
        $this->assertStringContainsString('ventas_historial_caja_fecha', $migration);
        $this->assertStringContainsString('pagos_historial_venta_metodo', $migration);
        $this->assertStringContainsString('usuarios_historial_tienda_activo_nombre', $migration);
    }
}
