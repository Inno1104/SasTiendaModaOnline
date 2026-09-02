<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_online_contadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->timestamps();
            $table->unique(['tienda_id', 'anio'], 'pedido_online_contador_unico');
        });

        Schema::create('pedidos_online', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->constrained('tiendas')->restrictOnDelete();
            $table->uuid('operacion_id');
            $table->char('operacion_hash', 64);
            $table->string('numero', 40);
            $table->string('estado', 30)->default('pendiente');
            $table->string('cliente_nombre', 100);
            $table->string('cliente_apellido', 100);
            $table->string('cliente_email', 150);
            $table->string('cliente_telefono', 21);
            $table->boolean('requiere_factura')->default(false);
            $table->string('tipo_identificacion', 30)->nullable();
            $table->string('identificacion', 30)->nullable();
            $table->string('razon_social', 180)->nullable();
            $table->decimal('subtotal', 14, 2);
            $table->decimal('descuento', 14, 2)->default(0);
            $table->decimal('envio', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->char('moneda', 3);
            $table->string('simbolo_moneda', 8);
            $table->timestamp('pagado_at')->nullable();
            $table->timestamp('preparando_at')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('entregado_at')->nullable();
            $table->timestamp('cancelado_at')->nullable();
            $table->string('motivo_cancelacion', 255)->nullable();
            $table->timestamps();

            $table->unique(['tienda_id', 'operacion_id'], 'pedido_online_operacion_unica');
            $table->unique(['tienda_id', 'numero'], 'pedido_online_numero_unico');
            $table->index(['tienda_id', 'estado', 'created_at', 'id'], 'pedido_online_estado_fecha');
            $table->index(['tienda_id', 'cliente_email', 'created_at'], 'pedido_online_cliente_fecha');
        });

        Schema::create('pedido_online_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->constrained('tiendas')->restrictOnDelete();
            $table->foreignId('pedido_online_id')->constrained('pedidos_online')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();
            $table->string('producto_codigo', 100);
            $table->string('producto_nombre', 255);
            $table->string('talla', 50);
            $table->string('color', 100);
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 14, 2);
            $table->decimal('descuento', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();

            $table->unique(['pedido_online_id', 'variante_id'], 'pedido_online_variante_unica');
            $table->index(['tienda_id', 'producto_id'], 'pedido_online_detalle_producto');
        });

        Schema::create('pedido_online_direcciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->constrained('tiendas')->restrictOnDelete();
            $table->foreignId('pedido_online_id')->constrained('pedidos_online')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('destinatario', 201);
            $table->string('telefono', 21)->nullable();
            $table->char('pais_codigo', 2);
            $table->string('provincia', 100);
            $table->string('ciudad', 100);
            $table->string('codigo_postal', 20)->nullable();
            $table->string('direccion', 255);
            $table->string('referencia', 255)->nullable();
            $table->timestamps();

            $table->unique(['pedido_online_id', 'tipo'], 'pedido_online_direccion_tipo_unica');
            $table->index(['tienda_id', 'pais_codigo', 'provincia', 'ciudad'], 'pedido_online_direccion_ubicacion');
        });

        $this->addIntegrityConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_online_direcciones');
        Schema::dropIfExists('pedido_online_detalles');
        Schema::dropIfExists('pedidos_online');
        Schema::dropIfExists('pedido_online_contadores');
    }

    private function addIntegrityConstraints(): void
    {
        $checks = [
            'pedidos_online' => [
                'pedido_online_estado_valido' => "estado IN ('pendiente','esperando_pago','pagado','preparando','enviado','entregado','cancelado')",
                'pedido_online_montos_validos' => 'subtotal >= 0 AND descuento >= 0 AND envio >= 0 AND total >= 0 AND total = subtotal - descuento + envio',
                'pedido_online_fiscal_valido' => '((requiere_factura = 0 AND tipo_identificacion IS NULL AND identificacion IS NULL AND razon_social IS NULL) OR (requiere_factura = 1 AND tipo_identificacion IS NOT NULL AND identificacion IS NOT NULL AND razon_social IS NOT NULL))',
            ],
            'pedido_online_detalles' => [
                'pedido_online_detalle_montos_validos' => 'cantidad > 0 AND precio_unitario >= 0 AND descuento >= 0 AND subtotal >= 0 AND subtotal = (precio_unitario * cantidad) - descuento',
            ],
            'pedido_online_direcciones' => [
                'pedido_online_direccion_tipo_valido' => "tipo IN ('entrega','fiscal')",
            ],
        ];

        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            foreach ($checks as $table => $tableChecks) {
                foreach ($tableChecks as $name => $expression) {
                    DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
                }
            }

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            foreach ($checks as $table => $tableChecks) {
                $expression = implode(' AND ', array_map(fn (string $check) => "({$check})", $tableChecks));
                foreach (['INSERT' => 'insert', 'UPDATE' => 'update'] as $event => $suffix) {
                    $qualified = preg_replace('/\b(estado|subtotal|descuento|envio|total|requiere_factura|tipo_identificacion|identificacion|razon_social|cantidad|precio_unitario|tipo)\b/', 'NEW.$1', $expression);
                    DB::unprepared("CREATE TRIGGER {$table}_integridad_{$suffix} BEFORE {$event} ON {$table} WHEN NOT ({$qualified}) BEGIN SELECT RAISE(ABORT, 'Registro de pedido online inconsistente'); END");
                }
            }
        }
    }
};
