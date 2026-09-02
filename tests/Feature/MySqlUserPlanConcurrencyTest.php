<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlUserPlanConcurrencyTest extends TestCase
{
    public function test_two_mysql_workers_cannot_exceed_the_user_limit(): void
    {
        if (getenv('USER_PLAN_MYSQL_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Requiere USER_PLAN_MYSQL_CONCURRENCY=1 y una base MySQL exclusiva terminada en _test.');
        }

        $database = (string) getenv('USER_PLAN_MYSQL_DATABASE');
        if (! str_ends_with($database, '_test')) {
            $this->fail('USER_PLAN_MYSQL_DATABASE debe terminar en _test para impedir daños sobre datos reales.');
        }

        $connection = $this->connection($database);
        config(['database.default' => 'mysql_user_plan_test', 'database.connections.mysql_user_plan_test' => $connection]);
        DB::purge('mysql_user_plan_test');
        Artisan::call('migrate:fresh', ['--database' => 'mysql_user_plan_test', '--force' => true]);

        $suffix = Str::lower(Str::random(8));
        $plan = Plan::create([
            'nombre' => 'Concurrente', 'slug' => 'concurrente-'.$suffix, 'precio' => 10,
            'max_productos' => -1, 'max_usuarios' => 2, 'max_ventas_mes' => -1, 'activo' => true,
        ]);
        $store = Tienda::create([
            'nombre' => 'Concurrencia', 'slug' => 'usuarios-'.$suffix,
            'plan_id' => $plan->id, 'estado' => 'activa',
        ]);
        $administratorRole = Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR]);
        Rol::firstOrCreate(['nombre' => 'Vendedor']);
        $administrator = User::create([
            'rol_id' => $administratorRole->id, 'tienda_id' => $store->id,
            'name' => 'Administrador', 'email' => $suffix.'@admin.test',
            'password' => 'password1', 'activo' => true, 'es_super_admin' => false,
        ]);

        $environment = array_merge($_SERVER, $_ENV, [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $connection['host'], 'DB_PORT' => $connection['port'],
            'DB_DATABASE' => $connection['database'], 'DB_USERNAME' => $connection['username'],
            'DB_PASSWORD' => $connection['password'], 'CACHE_DRIVER' => 'array',
            'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        ]);
        $command = fn (string $email): array => [
            PHP_BINARY, base_path('tests/Support/mysql_user_plan_worker.php'),
            (string) $store->id, (string) $administrator->id, $email,
        ];
        $first = new Process($command('first-'.$suffix.'@worker.test'), base_path(), $environment);
        $second = new Process($command('second-'.$suffix.'@worker.test'), base_path(), $environment);
        $first->start();
        $second->start();
        $first->wait();
        $second->wait();

        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
        $this->assertTrue($second->isSuccessful(), $second->getErrorOutput());
        $results = [trim($first->getOutput()), trim($second->getOutput())];
        sort($results);
        $this->assertSame(['created', 'rejected'], $results);
        $this->assertSame(2, User::query()->where('tienda_id', $store->id)->count());
    }

    /** @return array<string, mixed> */
    private function connection(string $database): array
    {
        return [
            'driver' => 'mysql',
            'host' => getenv('USER_PLAN_MYSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('USER_PLAN_MYSQL_PORT') ?: '3306',
            'database' => $database,
            'username' => getenv('USER_PLAN_MYSQL_USERNAME') ?: 'root',
            'password' => getenv('USER_PLAN_MYSQL_PASSWORD') ?: '',
            'unix_socket' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '', 'prefix_indexes' => true, 'strict' => true, 'engine' => 'InnoDB',
        ];
    }
}
