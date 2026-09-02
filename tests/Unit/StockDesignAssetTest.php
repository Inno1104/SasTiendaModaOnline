<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class StockDesignAssetTest extends TestCase
{
    public function test_stock_views_use_shared_assets_without_inline_presentation_or_handlers(): void
    {
        foreach (['index', 'history'] as $view) {
            $content = file_get_contents(dirname(__DIR__, 2)."/resources/views/stock/{$view}.blade.php");
            $this->assertStringContainsString("asset('css/stock.css')", $content);
            $this->assertStringContainsString("asset('js/stock.js')", $content);
            $this->assertStringNotContainsString('style=', $content);
            $this->assertDoesNotMatchRegularExpression('/\son(click|submit|change)=/i', $content);
        }
    }

    public function test_stock_ui_keeps_controlled_editing_confirmation_and_responsive_tables(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/stock/index.blade.php');
        $history = file_get_contents(dirname(__DIR__, 2).'/resources/views/stock/history.blade.php');
        $script = file_get_contents(dirname(__DIR__, 2).'/public/js/stock.js');
        $styles = file_get_contents(dirname(__DIR__, 2).'/public/css/stock.css');

        $this->assertStringContainsString('data-stock-edit', $view);
        $this->assertStringContainsString('data-stock-reason', $view);
        $this->assertStringContainsString("route('stock.history'", $view);
        $this->assertStringContainsString('data-confirm-message', $history);
        $this->assertStringContainsString('window.confirm', $script);
        $this->assertStringContainsString('@media(max-width:768px)', $styles);
        $this->assertStringContainsString('content:attr(data-label)', $styles);
    }
}
