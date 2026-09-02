<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ClienteCommercialAssetTest extends TestCase
{
    public function test_customer_detail_uses_paginated_history_and_aggregate_summary(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/ClienteController.php');
        $summary = file_get_contents(__DIR__.'/../../app/Services/ClienteCommercialSummary.php');
        $view = file_get_contents(__DIR__.'/../../resources/views/clientes/show.blade.php');

        $this->assertStringContainsString('->paginate(10)', $controller);
        $this->assertStringNotContainsString("load(['ventas'", $controller);
        $this->assertStringContainsString("where('estado', 'completada')", $summary);
        $this->assertStringContainsString('COUNT(*) as compras', $summary);
        $this->assertStringContainsString('@forelse($ventas as $v)', $view);
        $this->assertStringContainsString('$ventas->links()', $view);
    }
}
