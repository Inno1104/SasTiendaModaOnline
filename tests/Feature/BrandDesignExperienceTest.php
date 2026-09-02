<?php

namespace Tests\Feature;

use App\Models\Marca;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BrandDesignExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_view_exposes_accessible_controls_and_local_assets(): void
    {
        [$store, $user] = $this->storeAndUser('Administrador');
        $brand = $this->brand($store, 'Marca visual');

        $this->actingAs($user)->get(route('marcas.index'))
            ->assertOk()
            ->assertSee('css/marcas.css', false)
            ->assertSee('js/marcas.js', false)
            ->assertSee('Identidad del catálogo')
            ->assertSee('data-brand-delete', false)
            ->assertSee('aria-label="Editar Marca visual"', false)
            ->assertSee('aria-label="Eliminar Marca visual"', false)
            ->assertDontSee('onsubmit="return confirm', false);

        $this->assertSame($brand->id, Marca::query()->sole()->id);
    }

    public function test_read_only_view_hides_management_form_and_actions(): void
    {
        [$store, $seller] = $this->storeAndUser('Vendedor');
        $this->brand($store, 'Solo lectura');

        $this->actingAs($seller)->get(route('marcas.index'))
            ->assertOk()
            ->assertSee('Solo lectura')
            ->assertDontSee('Nueva marca')
            ->assertDontSee('data-brand-delete', false)
            ->assertDontSee('aria-label="Editar Solo lectura"', false);
    }

    public function test_empty_filtered_result_offers_a_clear_filters_action(): void
    {
        [, $user] = $this->storeAndUser('Administrador');

        $this->actingAs($user)->get(route('marcas.index', ['buscar' => 'inexistente']))
            ->assertOk()
            ->assertSee('No se encontraron marcas')
            ->assertSee('Limpiar filtros');
    }

    private function storeAndUser(string $role): array
    {
        $suffix = Str::lower(Str::random(10));
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'estado' => 'activa',
        ]);
        $user = User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => $role])->id,
            'tienda_id' => $store->id,
            'name' => $role.' '.$suffix,
            'email' => $suffix.'@brand-design.test',
            'password' => 'password',
            'activo' => true,
        ]);

        return [$store, $user];
    }

    private function brand(Tienda $store, string $name): Marca
    {
        return Marca::withoutGlobalScopes()->create([
            'tienda_id' => $store->id,
            'nombre' => $name,
            'nombre_normalizado' => mb_strtolower($name),
            'activo' => true,
        ]);
    }
}
