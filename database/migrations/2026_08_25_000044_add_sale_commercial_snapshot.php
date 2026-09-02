<?php

use App\Models\Venta;
use App\Services\SaleCommercialSnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->json('commercial_snapshot')->nullable()->after('operacion_hash');
            $table->char('commercial_snapshot_hash', 64)->nullable()->after('commercial_snapshot');
            $table->index(['tienda_id', 'created_at', 'id'], 'ventas_historial_orden_estable');
        });

        Venta::withoutGlobalScopes()
            ->with(['tienda', 'cliente', 'vendedor', 'detalles.producto', 'pagos'])
            ->whereNull('commercial_snapshot')
            ->chunkById(100, function ($sales): void {
                $snapshots = app(SaleCommercialSnapshot::class);

                foreach ($sales as $sale) {
                    $snapshot = $snapshots->capture($sale);
                    DB::table('ventas')->where('id', $sale->id)->update([
                        'commercial_snapshot' => json_encode(
                            $snapshot,
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                        ),
                        'commercial_snapshot_hash' => $snapshots->hash($snapshot),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('ventas_historial_orden_estable');
            $table->dropColumn(['commercial_snapshot', 'commercial_snapshot_hash']);
        });
    }
};
