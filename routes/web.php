<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankTransferController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuestCheckoutController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\MovimientoCajaController;
use App\Http\Controllers\OnlineOrderController;
use App\Http\Controllers\PedidoOnlineController;
use App\Http\Controllers\PosCatalogController;
use App\Http\Controllers\PosClienteController;
use App\Http\Controllers\PublicCartController;
use App\Http\Controllers\PublicCustomerLookupController;
use App\Http\Controllers\PublicStoreController;
use App\Http\Controllers\PublicOrderTrackingController;
use App\Http\Controllers\PublicOrderLookupController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\PurchaseCatalogController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\TiendaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// --- Utilidad: cargar datos de demostración visitando esta URL (solo en local) ---
Route::get('/seed-demo', function () {
    if (! app()->environment('local')) {
        abort(403);
    }
    Artisan::call('migrate', ['--force' => true]);
    Artisan::call('db:seed', [
        '--class' => 'DemoSeeder',
        '--force' => true,
    ]);

    return '<div style="font-family:sans-serif;text-align:center;padding:50px 20px;">'
        .'<h2 style="color:#16a34a;">&#10004; Plataforma SaaS lista</h2>'
        .'<p>Tienda demo + super admin + 10 registros por modulo.</p>'
        .'<div style="max-width:520px;margin:20px auto;text-align:left;background:#fdf2f8;border:1px solid #fbcfe8;padding:18px 22px;border-radius:12px;font-size:14px;line-height:1.9;">'
        .'<b>Admin de tienda:</b> admin@tiendamoda.com / admin123<br>'
        .'<b>Super admin:</b> superadmin@tiendamoda.com / super123'
        .'</div>'
        .'<p style="margin-top:10px;">'
        .'<a href="/login" style="color:#e8398c;font-weight:bold;margin:0 10px;">Iniciar sesion &rarr;</a>'
        .'<a href="/registro" style="color:#7b1fa2;font-weight:bold;margin:0 10px;">Registrar nueva tienda &rarr;</a>'
        .'</p>'
        .'<pre style="text-align:left;max-width:600px;margin:20px auto;background:#f3f4f6;padding:16px;border-radius:8px;font-size:12px;">'
        .htmlspecialchars(Artisan::output())
        .'</pre></div>';
});

// --- Utilidad: aplicar migraciones pendientes SIN borrar datos (solo local) ---
Route::get('/migrar', function () {
    if (! app()->environment('local')) {
        abort(403);
    }
    Artisan::call('migrate', ['--force' => true]);

    return '<div style="font-family:sans-serif;text-align:center;padding:50px;">'
        .'<h2 style="color:#16a34a;">&#10004; Migraciones aplicadas</h2>'
        .'<p><a href="/dashboard" style="color:#e8398c;font-weight:bold;">Ir al Dashboard &rarr;</a></p>'
        .'<pre style="text-align:left;max-width:600px;margin:20px auto;background:#f3f4f6;padding:16px;border-radius:8px;font-size:12px;">'
        .htmlspecialchars(Artisan::output())
        .'</pre></div>';
});

// Auth
Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::get('/login', [AuthController::class, 'showLogin']);
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Registro de nueva tienda (onboarding SaaS)
Route::get('/registro', [RegistroController::class, 'showForm'])->name('registro');
Route::post('/registro', [RegistroController::class, 'store'])->name('registro.store');

// Escaparate público de cada tienda. No requiere sesión.
Route::get('/tienda/{slug}', [PublicStoreController::class, 'show'])
    ->where('slug', '[A-Za-z0-9-]+')
    ->name('storefront.show');
Route::get('/tienda/{slug}/producto/{code}', [PublicStoreController::class, 'product'])
    ->where(['slug' => '[A-Za-z0-9-]+', 'code' => '[A-Za-z0-9-]+'])
    ->name('storefront.product.show');
Route::prefix('/tienda/{slug}/carrito')->where(['slug' => '[A-Za-z0-9-]+'])->group(function () {
    Route::get('/', [PublicCartController::class, 'show'])->name('storefront.cart.show');
    Route::post('/', [PublicCartController::class, 'store'])->name('storefront.cart.store');
    Route::patch('/{variant}', [PublicCartController::class, 'update'])->whereNumber('variant')->name('storefront.cart.update');
    Route::delete('/{variant}', [PublicCartController::class, 'destroy'])->whereNumber('variant')->name('storefront.cart.destroy');
    Route::delete('/', [PublicCartController::class, 'clear'])->name('storefront.cart.clear');
});
Route::post('/tienda/{slug}/checkout/datos', [GuestCheckoutController::class, 'store'])
    ->where('slug', '[A-Za-z0-9-]+')
    ->name('storefront.checkout.data.store');
Route::post('/tienda/{slug}/clientes/buscar-dni', PublicCustomerLookupController::class)
    ->where('slug', '[A-Za-z0-9-]+')
    ->middleware('throttle:20,1')
    ->name('storefront.customers.lookup');
Route::post('/tienda/{slug}/pedidos', [OnlineOrderController::class, 'store'])
    ->where('slug', '[A-Za-z0-9-]+')
    ->name('storefront.orders.store');
Route::get('/tienda/{slug}/mi-pedido', [PublicOrderLookupController::class, 'show'])->where('slug', '[A-Za-z0-9-]+')->name('storefront.orders.lookup');
Route::post('/tienda/{slug}/mi-pedido', [PublicOrderLookupController::class, 'store'])->where('slug', '[A-Za-z0-9-]+')->middleware('throttle:10,1')->name('storefront.orders.lookup.store');
Route::get('/tienda/{slug}/pago/transferencia/{operation}', [BankTransferController::class, 'show'])->whereUuid('operation')->name('storefront.transfer.show');
Route::post('/tienda/{slug}/pago/transferencia/{operation}', [BankTransferController::class, 'store'])->whereUuid('operation')->middleware('throttle:10,1')->name('storefront.transfer.store');
Route::get('/tienda/{slug}/pedido/{operation}/seguimiento', PublicOrderTrackingController::class)
    ->where(['slug' => '[A-Za-z0-9-]+', 'operation' => '[0-9a-fA-F-]{36}'])
    ->middleware(['signed', 'throttle:60,1'])
    ->name('storefront.orders.tracking');
Route::post('/tienda/{slug}/pedido/{operation}/calificacion', [PublicOrderTrackingController::class, 'rate'])
    ->where(['slug' => '[A-Za-z0-9-]+', 'operation' => '[0-9a-fA-F-]{36}'])
    ->middleware(['signed', 'throttle:5,1'])
    ->name('storefront.orders.rate');
Route::get('/tienda/{slug}/pedido/{operation}/guia-envio', [PublicOrderTrackingController::class, 'shippingGuide'])
    ->where(['slug' => '[A-Za-z0-9-]+', 'operation' => '[0-9a-fA-F-]{36}'])
    ->middleware(['signed', 'throttle:60,1'])
    ->name('storefront.orders.shipping-guide');
Route::get('/tienda/{slug}/pedido/{operation}/comprobante-venta', [PublicOrderTrackingController::class, 'saleReceipt'])
    ->where(['slug' => '[A-Za-z0-9-]+', 'operation' => '[0-9a-fA-F-]{36}'])
    ->middleware(['signed', 'throttle:60,1'])
    ->name('storefront.orders.sale-receipt');

// Panel Super-Admin (dueño de la plataforma)
Route::middleware(['auth', 'superadmin'])->prefix('admin')->group(function () {
    Route::get('/', [SuperAdminController::class, 'index'])->name('admin.index');
    Route::get('/perfil', [SuperAdminController::class, 'perfil'])->name('admin.perfil');
    Route::post('/perfil', [SuperAdminController::class, 'actualizarPerfil'])->name('admin.perfil.update');
    Route::post('/perfil/password', [SuperAdminController::class, 'cambiarPassword'])->name('admin.perfil.password');
    Route::get('/tiendas', [SuperAdminController::class, 'tiendas'])->name('admin.tiendas');

    // Gestión de planes
    Route::get('/planes', [SuperAdminController::class, 'planes'])->name('admin.planes');
    Route::post('/planes', [SuperAdminController::class, 'guardarPlan'])->name('admin.planes.store');
    Route::post('/planes/{plan}', [SuperAdminController::class, 'guardarPlan'])->name('admin.planes.update');
    Route::post('/planes/{plan}/toggle', [SuperAdminController::class, 'togglePlan'])->name('admin.planes.toggle');
    Route::post('/planes/{plan}/eliminar', [SuperAdminController::class, 'eliminarPlan'])->name('admin.planes.destroy');
    Route::post('/tiendas/{tienda}/estado', [SuperAdminController::class, 'cambiarEstado'])->name('admin.tiendas.estado');
    Route::post('/tiendas/{tienda}/plan', [SuperAdminController::class, 'cambiarPlan'])->name('admin.tiendas.plan');
    Route::post('/tiendas/{tienda}/extender', [SuperAdminController::class, 'extender'])->name('admin.tiendas.extender');
});

// Protected routes
Route::middleware(['auth', 'tienda'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/pedidos-online', [PedidoOnlineController::class, 'index'])->name('pedidos-online.index');
    Route::get('/pedidos-online/{pedido}', [PedidoOnlineController::class, 'show'])->whereNumber('pedido')->name('pedidos-online.show');
    Route::get('/pedidos-online/{pedido}/comprobante', [PedidoOnlineController::class, 'receipt'])->whereNumber('pedido')->name('pedidos-online.receipt');
    Route::post('/pedidos-online/{pedido}/verificar-pago', [PedidoOnlineController::class, 'verify'])->whereNumber('pedido')->name('pedidos-online.verify');
    Route::post('/pedidos-online/{pedido}/enviar-comprobante', [PedidoOnlineController::class, 'sendReceipt'])->whereNumber('pedido')->name('pedidos-online.send-receipt');
    Route::post('/pedidos-online/{pedido}/cancelar', [PedidoOnlineController::class, 'cancel'])->whereNumber('pedido')->name('pedidos-online.cancel');
    Route::patch('/pedidos-online/{pedido}/estado', [PedidoOnlineController::class, 'updateStatus'])->whereNumber('pedido')->name('pedidos-online.status');

    // Productos
    Route::resource('productos', ProductoController::class);

    // Clientes
    Route::get('/clientes/exportar', [ClienteController::class, 'export'])->name('clientes.export');
    Route::patch('/clientes/{cliente}/estado', [ClienteController::class, 'changeStatus'])->name('clientes.estado');
    Route::resource('clientes', ClienteController::class)->except(['destroy']);

    // Categorías
    Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
    Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
    Route::post('/categorias/{categoria}', [CategoriaController::class, 'update'])->name('categorias.update');
    Route::post('/categorias/{categoria}/del', [CategoriaController::class, 'destroy'])->name('categorias.destroy');

    // Marcas
    Route::get('/marcas', [MarcaController::class, 'index'])->name('marcas.index');
    Route::post('/marcas', [MarcaController::class, 'store'])->name('marcas.store');
    Route::post('/marcas/{marca}', [MarcaController::class, 'update'])->name('marcas.update');
    Route::post('/marcas/{marca}/del', [MarcaController::class, 'destroy'])->name('marcas.destroy');

    // Stock / Inventario
    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('/stock/reposicion/exportar', [StockController::class, 'replenishment'])->name('stock.replenishment');
    Route::get('/stock/{variante}/historial', [StockController::class, 'history'])->name('stock.history');
    Route::post('/stock/{variante}/conteos', [StockController::class, 'count'])->name('stock.count');
    Route::post('/stock/conteos/{conteo}/conciliar', [StockController::class, 'reconcile'])->name('stock.reconcile');
    Route::post('/stock/{variante}', [StockController::class, 'update'])->name('stock.update');

    // Compras
    Route::get('/compras', [CompraController::class, 'index'])->name('compras.index');
    Route::get('/compras/crear', [CompraController::class, 'create'])->name('compras.create');
    Route::get('/compras/catalogo', PurchaseCatalogController::class)->name('compras.catalogo');
    Route::post('/compras', [CompraController::class, 'store'])->name('compras.store');
    Route::post('/compras/{compra}/anular', [CompraController::class, 'cancel'])->name('compras.cancel');
    Route::get('/compras/{compra}', [CompraController::class, 'show'])->name('compras.show');

    // Usuarios (equipo de la tienda)
    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
    Route::post('/usuarios/{usuarioId}', [UsuarioController::class, 'update'])->whereNumber('usuarioId')->name('usuarios.update');
    Route::post('/usuarios/{usuarioId}/toggle', [UsuarioController::class, 'toggle'])->whereNumber('usuarioId')->name('usuarios.toggle');

    // Mi Tienda
    Route::get('/mi-tienda', [TiendaController::class, 'edit'])->name('tienda.edit');
    Route::post('/mi-tienda', [TiendaController::class, 'update'])->name('tienda.update');
    Route::post('/mi-tienda/publicacion', [TiendaController::class, 'updateStorefront'])->name('tienda.storefront.update');

    // Configuración
    Route::get('/configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');
    Route::post('/configuracion', [ConfiguracionController::class, 'update'])->name('configuracion.update');

    // Ventas
    Route::get('/ventas/clientes/buscar', [PosClienteController::class, 'search'])->name('ventas.clientes.buscar');
    Route::post('/ventas/clientes', [PosClienteController::class, 'store'])->name('ventas.clientes.store');
    Route::get('/ventas/catalogo', [PosCatalogController::class, 'index'])->name('ventas.catalogo');
    Route::get('/ventas/{venta}/comprobante', [VentaController::class, 'receipt'])->name('ventas.receipt');
    Route::resource('ventas', VentaController::class)->except(['edit', 'update', 'destroy']);
    Route::post('/ventas/{venta}/anular', [VentaController::class, 'anular'])->name('ventas.anular');
    Route::post('/ventas/{venta}/devolver', [VentaController::class, 'devolver'])->name('ventas.devolver');
    Route::get('/caja', [CajaController::class, 'index'])->name('caja.index');
    Route::get('/caja/exportar', [CajaController::class, 'export'])->name('caja.export');
    Route::get('/caja/{caja}', [CajaController::class, 'show'])->name('caja.show');
    Route::post('/caja/abrir', [CajaController::class, 'open'])->name('caja.open');
    Route::post('/caja/{caja}/cerrar', [CajaController::class, 'close'])->name('caja.close');
    Route::post('/caja/{caja}/movimientos', [MovimientoCajaController::class, 'store'])->name('caja.movimientos.store');

    // Proveedores
    Route::patch('/proveedores/{proveedor}/estado', [ProveedorController::class, 'changeStatus'])
        ->name('proveedores.estado');
    Route::resource('proveedores', ProveedorController::class)
        ->parameters(['proveedores' => 'proveedor'])
        ->except(['show']);

    // Reportes
    Route::get('/reportes/ventas', [ReporteController::class, 'ventas'])->name('reportes.ventas');
    Route::get('/reportes/inventario/exportar', [ReporteController::class, 'exportInventory'])->name('reportes.inventario.export');
    Route::get('/reportes/inventario', [ReporteController::class, 'inventario'])->name('reportes.inventario');
});
