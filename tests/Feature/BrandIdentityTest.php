<?php

namespace Tests\Feature;

use App\Models\Marca;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BrandIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_equivalent_names_are_rejected_inside_the_same_store(): void
    {
        [$user, $store] = $this->context();

        $this->actingAs($user)->post(route('marcas.store'), $this->payload('  Ropa   Urbana  '))
            ->assertSessionHas('success');
        $this->post(route('marcas.store'), $this->payload('ropa urbana'))
            ->assertSessionHasErrors('nombre');

        $this->assertSame(1, Marca::withoutGlobalScopes()->where('tienda_id', $store->id)->count());
        $this->assertDatabaseHas('marcas', [
            'tienda_id' => $store->id,
            'nombre' => 'Ropa Urbana',
            'nombre_normalizado' => 'ropa urbana',
        ]);
    }

    public function test_two_stores_can_use_the_same_brand_name(): void
    {
        [$firstUser, $firstStore] = $this->context();
        [$secondUser, $secondStore] = $this->context();

        $this->actingAs($firstUser)->post(route('marcas.store'), $this->payload('Nike'))
            ->assertSessionHas('success');
        $this->actingAs($secondUser)->post(route('marcas.store'), $this->payload('NIKE'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('marcas', ['tienda_id' => $firstStore->id, 'nombre_normalizado' => 'nike']);
        $this->assertDatabaseHas('marcas', ['tienda_id' => $secondStore->id, 'nombre_normalizado' => 'nike']);
    }

    public function test_update_keeps_its_identity_and_rejects_another_brand_name(): void
    {
        [$user, $store] = $this->context();
        $first = $this->brand($store, 'Adidas');
        $second = $this->brand($store, 'Puma');

        $this->actingAs($user)->post(route('marcas.update', $first), $this->payload('  ADIDAS  '))
            ->assertSessionHas('success');
        $this->post(route('marcas.update', $first), $this->payload(' puma '))
            ->assertSessionHasErrors('nombre');

        $this->assertSame('ADIDAS', $first->fresh()->nombre);
        $this->assertSame('puma', $second->fresh()->nombre_normalizado);
    }

    public function test_database_prevents_duplicate_normalized_names_in_one_store(): void
    {
        [, $store] = $this->context();
        $this->brand($store, 'Levi’s');

        $this->expectException(QueryException::class);
        $this->brand($store, 'Otra marca', 'levi’s');
    }

    public function test_new_store_initial_brands_have_normalized_identity(): void
    {
        $suffix = Str::lower(Str::random(10));

        $this->post(route('registro.store'), [
            'tienda' => 'Tienda '.$suffix,
            'name' => 'Propietario',
            'email' => $suffix.'@brand-registration.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'plan_id' => '',
        ])->assertRedirect(route('dashboard'));

        $brands = Marca::query()->get();
        $this->assertCount(4, $brands);
        $this->assertNotContains(null, $brands->pluck('nombre_normalizado')->all());
        $this->assertSame(
            $brands->pluck('nombre_normalizado')->sort()->values()->all(),
            $brands->pluck('nombre_normalizado')->unique()->sort()->values()->all()
        );
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(10));
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
            'email' => $suffix.'@brand-identity.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$user, $store];
    }

    private function payload(string $name): array
    {
        return ['nombre' => $name, 'activo' => true];
    }

    private function brand(Tienda $store, string $name, ?string $normalizedName = null): Marca
    {
        return Marca::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => $name,
            'nombre_normalizado' => $normalizedName ?? Str::lower(Str::squish($name)),
            'activo' => true,
        ]);
    }
}
