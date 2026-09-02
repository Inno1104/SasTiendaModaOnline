<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LocalChartJsAssetTest extends TestCase
{
    public function test_chart_js_is_available_locally_and_dashboard_does_not_use_the_cdn(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $asset = $projectRoot . '/public/js/vendor/chart.umd.min.js';
        $view = file_get_contents($projectRoot . '/resources/views/dashboard/index.blade.php');

        $this->assertFileExists($asset);
        $this->assertGreaterThan(100_000, filesize($asset));
        $this->assertStringContainsString("asset('js/vendor/chart.umd.min.js')", $view);
        $this->assertStringNotContainsString('cdnjs.cloudflare.com/ajax/libs/Chart.js', $view);
    }
}
