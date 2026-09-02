<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\StockMovimiento;
use App\Models\Tienda;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlInventoryConcurrencyTest extends TestCase
{
    public function test_two_real_mysql_workers_cannot_sell_the_same_last_unit(): void
    {
        if (getenv('INVENTORY_MYSQL_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Requiere INVENTORY_MYSQL_CONCURRENCY=1 y una base MySQL exclusiva terminada en _test.');
        }

        $database = (string) getenv('INVENTORY_MYSQL_DATABASE');
        if (! str_ends_with($database, '_test')) {
            $this->fail('INVENTORY_MYSQL_DATABASE debe terminar en _test para impedir daños sobre datos reales.');
        }

        $connection = [
            'driver' => 'mysql',
            'host' => getenv('INVENTORY_MYSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('INVENTORY_MYSQL_PORT') ?: '3306',
            'database' => $database,
            'username' => getenv('INVENTORY_MYSQL_USERNAME') ?: 'root',
            'password' => getenv('INVENTORY_MYSQL_PASSWORD') ?: '',
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
        ];
        config(['database.default' => 'mysql_inventory_test', 'database.connections.mysql_inventory_test' => $connection]);
        DB::purge('mysql_inventory_test');
        Artisan::call('migrate:fresh', ['--database' => 'mysql_inventory_test', '--force' => true]);

        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create(['nombre' => 'Concurrencia', 'slug' => 'concurrencia-'.$suffix, 'estado' => 'activa']);
        $category = Categoria::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Categoría concurrente',
            'nombre_normalizado' => 'categoria concurrente',
            'slug' => 'categoria-'.$suffix,
            'genero' => 'unisex',
            'activo' => true,
        ]);
        $product = Producto::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'categoria_id' => $category->id,
            'codigo' => 'MYSQL-'.Str::upper($suffix),
            'nombre' => 'Última unidad',
            'genero' => 'unisex',
            'tipo' => 'casual',
            'precio_compra' => 5,
            'precio_venta' => 10,
            'activo' => true,
        ]);
        $variant = ProductoVariante::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'producto_id' => $product->id,
            'talla' => 'M',
            'color' => 'Negro',
            'stock' => 1,
            'stock_minimo' => 1,
        ]);

        $environment = array_merge($_SERVER, $_ENV, [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $connection['host'],
            'DB_PORT' => $connection['port'],
            'DB_DATABASE' => $connection['database'],
            'DB_USERNAME' => $connection['username'],
            'DB_PASSWORD' => $connection['password'],
            'CACHE_DRIVER' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ]);
        $command = [PHP_BINARY, base_path('tests/Support/mysql_inventory_worker.php'), (string) $variant->id, (string) $store->id, '1'];
        $first = new Process($command, base_path(), $environment);
        $second = new Process($command, base_path(), $environment);
        $first->start();
        $second->start();
        $first->wait();
        $second->wait();

        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
        $this->assertTrue($second->isSuccessful(), $second->getErrorOutput());
        $results = [
            trim($first->getOutput()), trim($second->getOutput()),
        ];
        sort($results);
        $this->assertSame(['rejected', 'sold'], $results);
        $this->assertSame(0, $variant->fresh()->stock);
        $this->assertSame(1, StockMovimiento::withoutGlobalScopes()->where('variante_id', $variant->id)->count());
    }
}
