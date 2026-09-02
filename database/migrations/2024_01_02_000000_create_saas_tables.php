<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Planes de suscripción
        if (!Schema::hasTable('planes')) {
            Schema::create('planes', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('slug')->unique();
                $table->decimal('precio', 10, 2)->default(0);
                $table->integer('max_productos')->default(-1);   // -1 = ilimitado
                $table->integer('max_usuarios')->default(-1);
                $table->integer('max_ventas_mes')->default(-1);
                $table->text('descripcion')->nullable();
                $table->json('caracteristicas')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        // Tiendas (tenants)
        if (!Schema::hasTable('tiendas')) {
            Schema::create('tiendas', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('slug')->unique();
                $table->string('ruc')->nullable();
                $table->string('email')->nullable();
                $table->string('telefono')->nullable();
                $table->string('direccion')->nullable();
                $table->string('logo')->nullable();
                $table->foreignId('plan_id')->nullable()->constrained('planes')->nullOnDelete();
                $table->enum('estado', ['prueba', 'activa', 'suspendida'])->default('prueba');
                $table->date('fecha_inicio')->nullable();
                $table->date('fecha_vencimiento')->nullable();
                $table->timestamps();
            });
        }

        // users: tienda_id + es_super_admin
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'tienda_id')) {
                $table->foreignId('tienda_id')->nullable()->after('rol_id')->constrained('tiendas')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'es_super_admin')) {
                $table->boolean('es_super_admin')->default(false)->after('activo');
            }
        });

        // Agregar tienda_id a todas las tablas de datos por tienda
        $tablas = [
            'categorias', 'marcas', 'proveedores', 'productos', 'producto_variantes',
            'clientes', 'ventas', 'detalle_ventas', 'compras', 'detalle_compras', 'caja',
        ];
        foreach ($tablas as $t) {
            if (Schema::hasTable($t) && !Schema::hasColumn($t, 'tienda_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->unsignedBigInteger('tienda_id')->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        $tablas = [
            'categorias', 'marcas', 'proveedores', 'productos', 'producto_variantes',
            'clientes', 'ventas', 'detalle_ventas', 'compras', 'detalle_compras', 'caja',
        ];
        foreach ($tablas as $t) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, 'tienda_id')) {
                Schema::table($t, fn (Blueprint $table) => $table->dropColumn('tienda_id'));
            }
        }
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'tienda_id')) {
                $table->dropConstrainedForeignId('tienda_id');
            }
            if (Schema::hasColumn('users', 'es_super_admin')) {
                $table->dropColumn('es_super_admin');
            }
        });
        Schema::dropIfExists('tiendas');
        Schema::dropIfExists('planes');
    }
};
