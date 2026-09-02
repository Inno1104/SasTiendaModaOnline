<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Services\PurchaseNumberGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class PurchaseNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_a_sequential_correlative_per_store_and_year(): void
    {
        [$store] = $this->context('sequential');

        $first = DB::transaction(fn () => $this->generator()->next($store, 2026));
        $second = DB::transaction(fn () => $this->generator()->next($store, 2026));

        $this->assertSame("C-{$store->id}-2026-00001", $first);
        $this->assertSame("C-{$store->id}-2026-00002", $second);
        $this->assertDatabaseHas('compra_contadores', [
            'tienda_id' => $store->id,
            'anio' => 2026,
            'ultimo_numero' => 2,
        ]);
    }

    public function test_store_and_year_sequences_are_independent(): void
    {
        [$firstStore] = $this->context('first');
        [$secondStore] = $this->context('second');

        $first2025 = DB::transaction(fn () => $this->generator()->next($firstStore, 2025));
        $first2026 = DB::transaction(fn () => $this->generator()->next($firstStore, 2026));
        $second2026 = DB::transaction(fn () => $this->generator()->next($secondStore, 2026));

        $this->assertSame("C-{$firstStore->id}-2025-00001", $first2025);
        $this->assertSame("C-{$firstStore->id}-2026-00001", $first2026);
        $this->assertSame("C-{$secondStore->id}-2026-00001", $second2026);
        $this->assertDatabaseCount('compra_contadores', 3);
    }

    public function test_initial_counter_continues_after_the_highest_historical_number(): void
    {
        [$store, $supplier, $user] = $this->context('historical');
        $this->purchase($store, $supplier, $user, "C-{$store->id}-2025-00007", '2025-06-01');
        $this->purchase($store, $supplier, $user, "C-{$store->id}-2025-00012", '2025-07-01');
        $this->purchase($store, $supplier, $user, 'LEGACY-IGNORED', '2025-08-01');

        $number = DB::transaction(fn () => $this->generator()->next($store, 2025));

        $this->assertSame("C-{$store->id}-2025-00013", $number);
    }

    public function test_counter_rolls_back_with_the_failed_business_transaction(): void
    {
        [$store] = $this->context('rollback');

        try {
            DB::transaction(function () use ($store) {
                $this->generator()->next($store, 2026);
                throw new RuntimeException('Fallo simulado');
            });
        } catch (RuntimeException) {
            // El contador forma parte de la misma transacción de la compra.
        }

        $number = DB::transaction(fn () => $this->generator()->next($store, 2026));
        $this->assertSame("C-{$store->id}-2026-00001", $number);
    }

    public function test_generator_cannot_be_used_outside_a_transaction(): void
    {
        [$store] = $this->context('transaction');

        $this->expectException(LogicException::class);
        $this->generator()->next($store, 2026);
    }

    public function test_schema_enforces_one_counter_per_store_and_year(): void
    {
        [$store] = $this->context('constraint');
        $now = now();
        DB::table('compra_contadores')->insert([
            'tienda_id' => $store->id,
            'anio' => 2026,
            'ultimo_numero' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->expectException(QueryException::class);
        DB::table('compra_contadores')->insert([
            'tienda_id' => $store->id,
            'anio' => 2026,
            'ultimo_numero' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function generator(): PurchaseNumberGenerator
    {
        return app(PurchaseNumberGenerator::class);
    }

    /** @return array{Tienda, Proveedor, User} */
    private function context(string $suffix): array
    {
        $suffix .= '-'.Str::lower(Str::random(6));
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'estado' => 'activa',
        ]);
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $user = User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador '.$suffix,
            'email' => $suffix.'@purchase-number.test',
            'password' => 'password',
            'activo' => true,
        ]);
        $supplier = Proveedor::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => 'Proveedor '.$suffix,
            'activo' => true,
        ]);

        return [$store, $supplier, $user];
    }

    private function purchase(
        Tienda $store,
        Proveedor $supplier,
        User $user,
        string $number,
        string $date
    ): void {
        Compra::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'numero_compra' => $number,
            'proveedor_id' => $supplier->id,
            'user_id' => $user->id,
            'fecha' => $date,
            'total' => 0,
            'estado' => 'recibido',
        ]);
    }
}
