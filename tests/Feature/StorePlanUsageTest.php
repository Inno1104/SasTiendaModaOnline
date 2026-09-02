<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Services\StorePlanUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorePlanUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_displays_real_read_only_usage_and_near_limit_warnings(): void
    {
        [$admin, $store] = $this->context(10, 5);
        $category = Categoria::create([
            'tienda_id' => $store->id,
            'nombre' => 'Prendas',
            'nombre_normalizado' => 'prendas',
            'slug' => 'prendas',
            'genero' => 'unisex',
            'activo' => true,
        ]);
        foreach (range(1, 8) as $number) {
            Producto::create([
                'tienda_id' => $store->id,
                'categoria_id' => $category->id,
                'codigo' => 'PLAN-'.$number,
                'nombre' => 'Producto '.$number,
                'precio_venta' => 10,
                'activo' => true,
            ]);
        }
        foreach (range(1, 3) as $number) {
            $this->user($store, 'Vendedor', 'seller-'.$number);
        }

        $response = $this->actingAs($admin)->get(route('tienda.edit'))->assertOk();
        $response->assertSee('Plan Comercial')
            ->assertSee('8 / 10')
            ->assertSee('4 / 5')
            ->assertSee('Cerca del límite: quedan 2.')
            ->assertSee('Cerca del límite: quedan 1.')
            ->assertSee('administrados únicamente por el superadministrador')
            ->assertDontSee('name="plan_id"', false)
            ->assertDontSee('name="estado"', false)
            ->assertDontSee('name="fecha_vencimiento"', false);
    }

    public function test_unlimited_plan_has_explicit_indicators_without_fake_percentage(): void
    {
        [$admin] = $this->context(-1, -1);

        $this->actingAs($admin)->get(route('tienda.edit'))
            ->assertOk()
            ->assertSee('1 / Ilimitado')
            ->assertSee('Sin límite contratado');
    }

    public function test_reached_limit_is_reported_without_overflowing_progress(): void
    {
        [, $store] = $this->context(-1, 1);

        $usage = app(StorePlanUsage::class)->for($store)['users'];

        $this->assertSame('reached', $usage['state']);
        $this->assertSame(100, $usage['percentage']);
        $this->assertSame(0, $usage['remaining']);
    }

    public function test_protected_subscription_fields_remain_unchanged_on_profile_submission(): void
    {
        [$admin, $store] = $this->context(10, 5);
        $originalPlan = $store->plan_id;
        $originalExpiration = $store->fecha_vencimiento->toDateString();

        $this->actingAs($admin)->post(route('tienda.update'), [
            'nombre' => 'Nombre permitido',
            'plan_id' => Plan::create([
                'nombre' => 'Inyectado', 'slug' => 'inyectado', 'precio' => 1,
                'max_productos' => 999, 'max_usuarios' => 999, 'max_ventas_mes' => 999, 'activo' => true,
            ])->id,
            'estado' => 'suspendida',
            'fecha_vencimiento' => today()->subYear()->toDateString(),
            'max_productos' => 999,
            'max_usuarios' => 999,
        ])->assertSessionHasNoErrors();

        $store->refresh();
        $this->assertSame($originalPlan, $store->plan_id);
        $this->assertSame('activa', $store->estado);
        $this->assertSame($originalExpiration, $store->fecha_vencimiento->toDateString());
        $this->assertSame(10, (int) $store->plan->max_productos);
        $this->assertSame(5, (int) $store->plan->max_usuarios);
    }

    /** @return array{User, Tienda} */
    private function context(int $productLimit, int $userLimit): array
    {
        $suffix = Str::lower(Str::random(8));
        $plan = Plan::create([
            'nombre' => 'Plan Comercial',
            'slug' => 'plan-'.$suffix,
            'precio' => 20,
            'max_productos' => $productLimit,
            'max_usuarios' => $userLimit,
            'max_ventas_mes' => -1,
            'activo' => true,
        ]);
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'plan_id' => $plan->id,
            'estado' => 'activa',
            'fecha_vencimiento' => today()->addDays(30),
        ]);

        return [$this->user($store, Rol::ADMINISTRATOR, 'admin'), $store];
    }

    private function user(Tienda $store, string $role, string $prefix): User
    {
        $suffix = Str::lower(Str::random(8));

        return User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => $role])->id,
            'tienda_id' => $store->id,
            'name' => $prefix,
            'email' => $prefix.'-'.$suffix.'@plan-usage.test',
            'password' => 'password1',
            'activo' => true,
            'es_super_admin' => false,
        ]);
    }
}
