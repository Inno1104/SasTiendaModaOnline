<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\Marca;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Limpieza (re-ejecutable) ----
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['detalle_ventas', 'ventas', 'detalle_compras', 'compras', 'caja', 'producto_variantes', 'productos', 'clientes', 'proveedores', 'categorias', 'marcas'] as $t) {
            if (DB::getSchemaBuilder()->hasTable($t)) {
                DB::table($t)->truncate();
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ---- Roles ----
        $rolAdmin = Rol::firstOrCreate(['nombre' => 'Administrador'], ['descripcion' => 'Acceso total a la tienda']);
        Rol::firstOrCreate(['nombre' => 'Vendedor'], ['descripcion' => 'Gestión de ventas y clientes']);
        Rol::firstOrCreate(['nombre' => 'Almacén'], ['descripcion' => 'Gestión de inventario']);

        // ---- Planes de suscripción ----
        $planesData = [
            ['Básico',  'basico',  49,  50,   2,  200, 'Ideal para empezar', ['1 sucursal', 'Soporte por correo', 'Reportes básicos']],
            ['Pro',     'pro',     99,  500,  8,  -1,  'Para tiendas en crecimiento', ['3 sucursales', 'Soporte prioritario', 'Reportes avanzados', 'Gestión de compras']],
            ['Premium', 'premium', 199, -1,  -1,  -1,  'Sin límites', ['Sucursales ilimitadas', 'Soporte 24/7', 'Todos los reportes', 'API e integraciones']],
        ];
        $planes = [];
        foreach ($planesData as $p) {
            $planes[$p[1]] = Plan::updateOrCreate(['slug' => $p[1]], [
                'nombre' => $p[0], 'precio' => $p[2], 'max_productos' => $p[3],
                'max_usuarios' => $p[4], 'max_ventas_mes' => $p[5], 'descripcion' => $p[6],
                'caracteristicas' => $p[7], 'activo' => true,
            ]);
        }

        // ---- Super administrador de la plataforma ----
        User::updateOrCreate(['email' => 'superadmin@tiendamoda.com'], [
            'rol_id' => $rolAdmin->id, 'tienda_id' => null, 'name' => 'Super Admin',
            'password' => Hash::make('super123'), 'activo' => true, 'es_super_admin' => true,
        ]);

        // ---- Tienda demo ----
        $tienda = Tienda::updateOrCreate(['slug' => 'tienda-demo'], [
            'nombre' => 'Moda Demo', 'email' => 'admin@tiendamoda.com', 'telefono' => '01-7000000',
            'direccion' => 'Av. La Moda 123, Lima', 'plan_id' => $planes['pro']->id,
            'estado' => 'activa', 'fecha_inicio' => Carbon::today()->subMonths(2),
            'fecha_vencimiento' => Carbon::today()->addYear(),
        ]);
        $tid = $tienda->id;

        // ---- Usuario admin de la tienda demo ----
        $admin = User::updateOrCreate(['email' => 'admin@tiendamoda.com'], [
            'rol_id' => $rolAdmin->id, 'tienda_id' => $tid, 'name' => 'Administrador',
            'password' => Hash::make('admin123'), 'activo' => true, 'es_super_admin' => false,
        ]);

        // ---- Vendedores de la tienda ----
        $vendedorRol = Rol::where('nombre', 'Vendedor')->first()->id;
        $vendedores = [$admin];
        foreach (['Lucía Torres', 'Carlos Mendoza', 'Ana Ramírez'] as $i => $nom) {
            $vendedores[] = User::updateOrCreate(['email' => 'vendedor'.($i + 1).'@tiendamoda.com'], [
                'rol_id' => $vendedorRol, 'tienda_id' => $tid, 'name' => $nom,
                'password' => Hash::make('vendedor123'), 'activo' => true,
            ]);
        }

        // ---- Categorías ----
        $catsData = [
            ['Camisas', 'camisas', 'caballeros'], ['Blusas', 'blusas', 'damas'], ['Pantalones', 'pantalones', 'unisex'],
            ['Vestidos', 'vestidos', 'damas'], ['Ropa Deportiva', 'deportiva', 'unisex'], ['Ropa Niños', 'ninos', 'ninos'],
            ['Chaquetas', 'chaquetas', 'unisex'], ['Jeans', 'jeans', 'unisex'], ['Faldas', 'faldas', 'damas'], ['Accesorios', 'accesorios', 'unisex'],
        ];
        $categorias = [];
        foreach ($catsData as $c) {
            $categorias[$c[1]] = Categoria::create(['tienda_id' => $tid, 'nombre' => $c[0], 'slug' => $c[1], 'genero' => $c[2]]);
        }

        // ---- Marcas ----
        $marcas = [];
        foreach (['Zara', 'H&M', 'Adidas', 'Nike', 'Polo', 'Tommy Hilfiger', 'Levis', 'Forever 21'] as $m) {
            $marcas[] = Marca::create([
                'tienda_id' => $tid,
                'nombre' => $m,
                'nombre_normalizado' => Str::lower(Str::squish($m)),
            ]);
        }

        // ---- Proveedores (10) ----
        $provData = [
            ['Textiles Andinos SAC', '20512345678', 'Lima'], ['Moda Import EIRL', '20587654321', 'Lima'],
            ['Distribuidora Fashion Perú', '20498765432', 'Arequipa'], ['Confecciones del Sur', '20456781234', 'Cusco'],
            ['Comercial Estilo SAC', '20467812345', 'Trujillo'], ['Importaciones Glamour', '20478123456', 'Lima'],
            ['Ropa & Más Distribución', '20489234567', 'Piura'], ['Tendencias Textiles SAC', '20490345678', 'Chiclayo'],
            ['Grupo Vestir Perú', '20501456789', 'Lima'], ['Almacenes Bella Moda', '20512567890', 'Ica'],
        ];
        $proveedores = [];
        foreach ($provData as $i => $p) {
            $proveedores[] = Proveedor::create([
                'tienda_id' => $tid, 'nombre' => $p[0], 'ruc' => $p[1],
                'email' => 'ventas'.($i + 1).'@proveedor.com', 'telefono' => '01-'.rand(2000000, 7999999),
                'direccion' => 'Av. Industrial '.rand(100, 999), 'ciudad' => $p[2], 'pais' => 'Perú',
                'contacto' => ['Juan Pérez', 'María López', 'Pedro Díaz', 'Rosa Gil', 'Luis Vega'][rand(0, 4)], 'activo' => true,
            ]);
        }

        // ---- Productos (10) + variantes ----
        $prodData = [
            ['Camisa Slim Fit Cuadros', 'caballeros', 'casual', 45, 89.90, 'camisas'],
            ['Blusa Floral Manga Larga', 'damas', 'casual', 38, 75.00, 'blusas'],
            ['Pantalón Chino Beige', 'caballeros', 'casual', 55, 120.00, 'pantalones'],
            ['Vestido Casual Verano', 'damas', 'casual', 60, 145.00, 'vestidos'],
            ['Conjunto Deportivo Running', 'unisex', 'deportivo', 70, 159.90, 'deportiva'],
            ['Polo Niño Estampado', 'ninos', 'casual', 20, 39.90, 'ninos'],
            ['Chaqueta Jean Oversize', 'unisex', 'casual', 80, 189.00, 'chaquetas'],
            ['Jean Skinny Azul', 'damas', 'casual', 50, 110.00, 'jeans'],
            ['Falda Plisada Midi', 'damas', 'formal', 42, 95.00, 'faldas'],
            ['Gorra Snapback Urbana', 'unisex', 'casual', 15, 45.00, 'accesorios'],
        ];
        $tallas = ['S', 'M', 'L', 'XL'];
        $colores = ['Negro', 'Blanco', 'Azul', 'Rojo', 'Beige', 'Verde'];
        $productos = [];
        foreach ($prodData as $i => $pr) {
            $cat = $categorias[$pr[5]] ?? reset($categorias);
            $producto = Producto::create([
                'tienda_id' => $tid, 'categoria_id' => $cat->id, 'marca_id' => $marcas[array_rand($marcas)]->id,
                'proveedor_id' => $proveedores[array_rand($proveedores)]->id,
                'codigo' => 'PRD-'.str_pad($i + 1, 4, '0', STR_PAD_LEFT), 'nombre' => $pr[0],
                'descripcion' => $pr[0].' de excelente calidad.', 'genero' => $pr[1], 'tipo' => $pr[2],
                'precio_compra' => $pr[3], 'precio_venta' => $pr[4], 'activo' => true, 'destacado' => $i < 4,
            ]);
            foreach (range(0, rand(1, 2)) as $v) {
                ProductoVariante::create([
                    'tienda_id' => $tid, 'producto_id' => $producto->id, 'talla' => $tallas[$v % 4],
                    'color' => $colores[($i + $v) % 6], 'codigo_barra' => '78'.rand(10000000, 99999999),
                    'stock' => ($i % 4 == 0 && $v == 0) ? rand(0, 4) : rand(8, 60), 'stock_minimo' => 5,
                ]);
            }
            $productos[] = $producto;
        }

        // ---- Clientes (10) ----
        $cliData = [
            ['Sofía', 'Gutiérrez', 'F', 'Lima'], ['Mateo', 'Rojas', 'M', 'Arequipa'], ['Valentina', 'Flores', 'F', 'Trujillo'],
            ['Diego', 'Castro', 'M', 'Lima'], ['Camila', 'Vargas', 'F', 'Cusco'], ['Sebastián', 'Núñez', 'M', 'Piura'],
            ['Isabella', 'Ramos', 'F', 'Lima'], ['Joaquín', 'Salazar', 'M', 'Chiclayo'], ['Antonella', 'Reyes', 'F', 'Ica'], ['Thiago', 'Paredes', 'M', 'Lima'],
        ];
        $clientes = [];
        foreach ($cliData as $i => $c) {
            $clientes[] = Cliente::create([
                'tienda_id' => $tid, 'codigo' => 'CLI-'.str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'nombre' => $c[0], 'apellido' => $c[1], 'dni' => (string) rand(40000000, 79999999),
                'email' => strtolower($c[0].'.'.$c[1]).'@gmail.com', 'telefono' => '9'.rand(10000000, 99999999),
                'ciudad' => $c[3], 'genero' => $c[2], 'puntos' => rand(0, 500), 'activo' => true,
                'created_at' => Carbon::now()->subDays(rand(5, 120)),
            ]);
        }

        // ---- Ventas (10) con detalle ----
        $diasAtras = [0, 0, 1, 2, 3, 4, 5, 1, 8, 12];
        $metodos = ['efectivo', 'tarjeta', 'yape', 'plin', 'transferencia'];
        $compr = ['boleta', 'factura', 'ticket'];
        foreach ($diasAtras as $i => $d) {
            $fecha = Carbon::now()->subDays($d)->setTime(rand(9, 20), rand(0, 59));
            $venta = Venta::create([
                'tienda_id' => $tid, 'numero_venta' => 'V-'.date('Y').'-'.str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                'cliente_id' => $clientes[array_rand($clientes)]->id, 'user_id' => $vendedores[array_rand($vendedores)]->id,
                'fecha' => $fecha->toDateString(), 'tipo_comprobante' => $compr[array_rand($compr)],
                'serie' => 'B001', 'correlativo' => str_pad($i + 1, 6, '0', STR_PAD_LEFT),
                'metodo_pago' => $metodos[array_rand($metodos)], 'estado' => 'completada',
                'created_at' => $fecha, 'updated_at' => $fecha,
            ]);
            $subtotalVenta = 0;
            $usados = [];
            foreach (range(1, rand(1, 3)) as $l) {
                $prod = $productos[array_rand($productos)];
                if (in_array($prod->id, $usados)) {
                    continue;
                }
                $usados[] = $prod->id;
                $variante = $prod->variantes()->inRandomOrder()->first();
                $cantidad = rand(1, 3);
                $precio = (float) $prod->precio_venta;
                $subLinea = round($precio * $cantidad, 2);
                $subtotalVenta += $subLinea;
                DetalleVenta::create([
                    'tienda_id' => $tid, 'venta_id' => $venta->id, 'producto_id' => $prod->id,
                    'variante_id' => $variante?->id, 'producto_nombre' => $prod->nombre,
                    'talla' => $variante?->talla, 'color' => $variante?->color, 'cantidad' => $cantidad,
                    'precio_unitario' => $precio, 'descuento' => 0, 'subtotal' => $subLinea,
                ]);
                if ($variante) {
                    $variante->decrement('stock', min($cantidad, $variante->stock));
                }
            }
            $total = round($subtotalVenta, 2);
            $igv = round($total - ($total / 1.18), 2);
            $venta->update(['subtotal' => round($total - $igv, 2), 'igv' => $igv, 'total' => $total, 'monto_pagado' => $total, 'vuelto' => 0]);
        }

        // ---- Compras (10) + detalle ----
        for ($i = 0; $i < 10; $i++) {
            $fecha = Carbon::now()->subDays(rand(1, 40));
            $compraId = DB::table('compras')->insertGetId([
                'tienda_id' => $tid, 'numero_compra' => 'C-'.date('Y').'-'.str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                'proveedor_id' => $proveedores[array_rand($proveedores)]->id, 'user_id' => $admin->id,
                'fecha' => $fecha->toDateString(), 'estado' => 'recibido', 'observaciones' => 'Reposición de inventario',
                'created_at' => $fecha, 'updated_at' => $fecha,
            ]);
            $totalCompra = 0;
            foreach (range(1, rand(2, 4)) as $l) {
                $prod = $productos[array_rand($productos)];
                $variante = $prod->variantes()->inRandomOrder()->first();
                $cantidad = rand(10, 50);
                $precio = (float) $prod->precio_compra;
                $subLinea = round($precio * $cantidad, 2);
                $totalCompra += $subLinea;
                DB::table('detalle_compras')->insert([
                    'tienda_id' => $tid, 'compra_id' => $compraId, 'producto_id' => $prod->id,
                    'variante_id' => $variante?->id, 'cantidad' => $cantidad, 'precio_unitario' => $precio,
                    'subtotal' => $subLinea, 'created_at' => $fecha, 'updated_at' => $fecha,
                ]);
            }
            DB::table('compras')->where('id', $compraId)->update(['total' => round($totalCompra, 2)]);
        }

        // ---- Caja (10 movimientos) ----
        for ($i = 0; $i < 10; $i++) {
            $fecha = Carbon::now()->subDays($i);
            $ventasDia = Venta::where('tienda_id', $tid)->whereDate('fecha', $fecha->toDateString())->where('estado', 'completada')->sum('total');
            DB::table('caja')->insert([
                'tienda_id' => $tid, 'user_id' => $vendedores[array_rand($vendedores)]->id, 'fecha' => $fecha->toDateString(),
                'monto_inicial' => 200, 'monto_final' => 200 + $ventasDia, 'total_ventas' => $ventasDia,
                'total_egresos' => rand(0, 80), 'estado' => $i == 0 ? 'abierta' : 'cerrada',
                'apertura' => $fecha->copy()->setTime(9, 0), 'cierre' => $i == 0 ? null : $fecha->copy()->setTime(20,0),
                'created_at' => $fecha, 'updated_at' => $fecha,
            ]);
        }

        $this->command->info('OK DemoSeeder: tienda demo + super admin + 10 registros por modulo.');
    }
}
