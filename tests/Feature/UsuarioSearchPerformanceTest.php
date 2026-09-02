<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsuarioSearchPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_are_validated_before_querying(): void
    {
        [$administrator] = $this->context();

        $this->actingAs($administrator)->get(route('usuarios.index', [
            'buscar' => str_repeat('a', 101),
            'estado' => 'eliminado',
            'rol_id' => 999999,
            'orden' => 'aleatorio',
            'por_pagina' => 500,
            'page' => 0,
        ]))->assertSessionHasErrors(['buscar', 'estado', 'rol_id', 'orden', 'por_pagina', 'page']);
    }

    public function test_search_covers_name_email_and_phone_and_treats_wildcards_literally(): void
    {
        [$administrator, $store] = $this->context();
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);
        $target = $this->user($store, $sellerRole, 'Ana 100%_Real', 'ana@example.test', '0991234567');
        $this->user($store, $sellerRole, 'Ana 100XReal', 'different@example.test', '0980000000');

        foreach (['%_', 'ANA@EXAMPLE.TEST', '099-123'] as $term) {
            $users = $this->actingAs($administrator)
                ->get(route('usuarios.index', ['buscar' => $term]))
                ->assertOk()
                ->viewData('usuarios');

            $this->assertSame(1, $users->total(), "La búsqueda '{$term}' devolvió resultados incorrectos.");
            $this->assertSame($target->id, $users->first()->id);
        }
    }

    public function test_status_role_and_store_filters_are_combined(): void
    {
        [$administrator, $store] = $this->context();
        [, $foreignStore] = $this->context();
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);
        $match = $this->user($store, $sellerRole, 'Vendedor activo', 'active@example.test');
        $this->user($store, $sellerRole, 'Vendedor inactivo', 'inactive@example.test', null, false);
        $this->user($foreignStore, $sellerRole, 'Vendedor extranjero', 'foreign@example.test');

        $users = $this->actingAs($administrator)->get(route('usuarios.index', [
            'buscar' => 'vendedor',
            'estado' => 'activo',
            'rol_id' => $sellerRole->id,
        ]))->assertOk()->viewData('usuarios');

        $this->assertSame(1, $users->total());
        $this->assertSame($match->id, $users->first()->id);
    }

    public function test_pagination_has_stable_order_preserves_filters_and_uses_real_plan_consumption(): void
    {
        [$administrator, $store] = $this->context();
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);
        foreach (range(1, 16) as $number) {
            $this->user($store, $sellerRole, sprintf('Usuario %02d', $number), "user{$number}@example.test");
        }

        $filters = ['rol_id' => $sellerRole->id, 'orden' => 'nombre_desc', 'por_pagina' => 10];
        /** @var LengthAwarePaginator $firstPage */
        $response = $this->actingAs($administrator)->get(route('usuarios.index', $filters))->assertOk();
        $firstPage = $response->viewData('usuarios');

        $this->assertSame(16, $firstPage->total());
        $this->assertCount(10, $firstPage->items());
        $this->assertSame('Usuario 16', $firstPage->first()->name);
        $this->assertSame(17, $response->viewData('usuariosRegistrados'));

        parse_str((string) parse_url((string) $firstPage->nextPageUrl(), PHP_URL_QUERY), $nextQuery);
        $this->assertSame((string) $sellerRole->id, $nextQuery['rol_id']);
        $this->assertSame('nombre_desc', $nextQuery['orden']);
        $this->assertSame('10', $nextQuery['por_pagina']);
        $this->assertSame('2', $nextQuery['page']);
    }

    public function test_listing_has_bounded_queries_and_schema_has_required_indexes(): void
    {
        [$administrator, $store] = $this->context();
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);
        foreach (range(1, 40) as $number) {
            $this->user($store, $sellerRole, sprintf('Rendimiento %02d', $number), "performance{$number}@example.test");
        }

        $administrator->load('rol', 'tienda.plan');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($administrator)->get(route('usuarios.index'));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertCount(15, $response->viewData('usuarios')->items());
        $this->assertLessThanOrEqual(8, $queryCount, "El listado ejecutó {$queryCount} consultas; puede existir un N+1.");

        $indexes = collect(Schema::getIndexes('users'))->pluck('name');
        $this->assertContains('usuarios_tienda_rol_estado_nombre', $indexes);
        $this->assertContains('usuarios_tienda_fecha', $indexes);
    }

    public function test_explain_uses_the_composite_index_for_tenant_role_and_status_query(): void
    {
        [$administrator, $store] = $this->context();
        $sellerRole = Rol::firstOrCreate(['nombre' => 'Vendedor']);

        $plan = collect(DB::select(
            'EXPLAIN QUERY PLAN SELECT id FROM users WHERE tienda_id = ? AND es_super_admin = ? AND rol_id = ? AND activo = ? ORDER BY name, id LIMIT 15',
            [$store->id, 0, $sellerRole->id, 1]
        ))->map(fn (object $row): string => mb_strtolower((string) ($row->detail ?? '')))->implode(' ');

        $this->assertStringContainsString('usuarios_tienda_rol_estado_nombre', $plan);
        $this->assertTrue($administrator->isAdmin());
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create(['nombre' => 'Tienda '.$suffix, 'slug' => 'tienda-'.$suffix, 'estado' => 'activa']);
        $role = Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR]);

        return [$this->user($store, $role, 'Administrador', $suffix.'@search-admin.test'), $store];
    }

    private function user(
        Tienda $store,
        Rol $role,
        string $name,
        string $email,
        ?string $phone = null,
        bool $active = true
    ): User {
        return User::create([
            'rol_id' => $role->id,
            'tienda_id' => $store->id,
            'name' => $name,
            'email' => $email,
            'telefono' => $phone,
            'password' => 'password1',
            'activo' => $active,
            'es_super_admin' => false,
        ]);
    }
}
