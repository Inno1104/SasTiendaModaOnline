<?php

namespace App\Providers;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\DetalleVenta;
use App\Models\Marca;
use App\Models\PagoVenta;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Tienda;
use App\Models\User;
use App\Models\Venta;
use App\Observers\DashboardCacheObserver;
use App\Policies\CajaPolicy;
use App\Policies\ClientePolicy;
use App\Policies\CompraPolicy;
use App\Policies\MarcaPolicy;
use App\Policies\ProductoPolicy;
use App\Policies\ProductoVariantePolicy;
use App\Policies\ProveedorPolicy;
use App\Policies\TiendaPolicy;
use App\Policies\UsuarioPolicy;
use App\Policies\VentaPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(Venta::class, VentaPolicy::class);
        Gate::policy(Caja::class, CajaPolicy::class);
        Gate::policy(Cliente::class, ClientePolicy::class);
        Gate::policy(Compra::class, CompraPolicy::class);
        Gate::policy(Marca::class, MarcaPolicy::class);
        Gate::policy(Producto::class, ProductoPolicy::class);
        Gate::policy(ProductoVariante::class, ProductoVariantePolicy::class);
        Gate::policy(Proveedor::class, ProveedorPolicy::class);
        Gate::policy(Tienda::class, TiendaPolicy::class);
        Gate::policy(User::class, UsuarioPolicy::class);
        Paginator::useBootstrap();

        foreach ([Venta::class, DetalleVenta::class, PagoVenta::class, Cliente::class, Categoria::class, Producto::class, ProductoVariante::class] as $model) {
            $model::observe(DashboardCacheObserver::class);
        }

        View::composer('*', function ($view) {
            $tienda = auth()->check() ? auth()->user()->tienda : null;
            $view->with('codigoMoneda', $tienda?->moneda ?: 'USD');
            $view->with('simboloMoneda', $tienda?->simbolo_moneda ?: '$');
        });
    }
}
