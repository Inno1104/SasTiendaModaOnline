<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clientes
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('dni')->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->enum('genero', ['M', 'F', 'otro'])->nullable();
            $table->decimal('puntos', 10, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Ventas
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->string('numero_venta')->unique();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->foreignId('user_id')->constrained('users'); // vendedor
            $table->date('fecha');
            $table->enum('tipo_comprobante', ['boleta', 'factura', 'ticket'])->default('boleta');
            $table->string('serie')->nullable();
            $table->string('correlativo')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('igv', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->enum('metodo_pago', ['efectivo', 'tarjeta', 'transferencia', 'yape', 'plin', 'mixto'])->default('efectivo');
            $table->decimal('monto_pagado', 10, 2)->default(0);
            $table->decimal('vuelto', 10, 2)->default(0);
            $table->enum('estado', ['pendiente', 'completada', 'anulada', 'devuelta'])->default('completada');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // Detalle de ventas
        Schema::create('detalle_ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->onDelete('cascade');
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes');
            $table->string('producto_nombre');
            $table->string('talla')->nullable();
            $table->string('color')->nullable();
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });

        // Compras / Entradas de inventario
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('numero_compra')->unique();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('user_id')->constrained('users');
            $table->date('fecha');
            $table->decimal('total', 10, 2)->default(0);
            $table->enum('estado', ['pendiente', 'recibido', 'anulado'])->default('recibido');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('detalle_compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->onDelete('cascade');
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });

        // Caja / Movimientos de caja
        Schema::create('caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->date('fecha');
            $table->decimal('monto_inicial', 10, 2)->default(0);
            $table->decimal('monto_final', 10, 2)->nullable();
            $table->decimal('total_ventas', 10, 2)->default(0);
            $table->decimal('total_egresos', 10, 2)->default(0);
            $table->enum('estado', ['abierta', 'cerrada'])->default('abierta');
            $table->timestamp('apertura')->nullable();
            $table->timestamp('cierre')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_compras');
        Schema::dropIfExists('compras');
        Schema::dropIfExists('detalle_ventas');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('caja');
    }
};
