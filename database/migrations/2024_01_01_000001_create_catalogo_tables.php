<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Categorías de productos
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->text('descripcion')->nullable();
            $table->string('imagen')->nullable();
            $table->enum('genero', ['damas', 'caballeros', 'ninos', 'unisex'])->default('unisex');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Subcategorías
        Schema::create('subcategorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained()->onDelete('cascade');
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Proveedores
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('ruc')->unique()->nullable();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('pais')->default('Perú');
            $table->string('contacto')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Marcas
        Schema::create('marcas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('logo')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Productos
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias');
            $table->foreignId('subcategoria_id')->nullable()->constrained('subcategorias');
            $table->foreignId('marca_id')->nullable()->constrained('marcas');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores');
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->enum('genero', ['damas', 'caballeros', 'ninos', 'unisex'])->default('unisex');
            $table->enum('tipo', ['casual', 'deportivo', 'formal', 'otro'])->default('casual');
            $table->decimal('precio_compra', 10, 2)->default(0);
            $table->decimal('precio_venta', 10, 2);
            $table->decimal('precio_oferta', 10, 2)->nullable();
            $table->string('imagen')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('destacado')->default(false);
            $table->timestamps();
        });

        // Variantes (talla + color + stock)
        Schema::create('producto_variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained()->onDelete('cascade');
            $table->string('talla'); // XS, S, M, L, XL, XXL, 28, 30, 32, etc.
            $table->string('color');
            $table->string('codigo_barra')->nullable();
            $table->integer('stock')->default(0);
            $table->integer('stock_minimo')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_variantes');
        Schema::dropIfExists('productos');
        Schema::dropIfExists('marcas');
        Schema::dropIfExists('proveedores');
        Schema::dropIfExists('subcategorias');
        Schema::dropIfExists('categorias');
    }
};
