<?php

namespace App\Models;

use App\Support\StoreLocation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Tienda extends Model
{
    protected $table = 'tiendas';

    protected $fillable = [
        'nombre', 'slug', 'ruc', 'email', 'telefono', 'whatsapp', 'direccion',
        'pais_codigo', 'provincia', 'ciudad',
        'sitio_web', 'redes_sociales',
        'publicada', 'descripcion_publica', 'color_primario', 'horarios', 'condiciones_venta',
        'logo', 'plan_id', 'estado', 'fecha_inicio', 'fecha_vencimiento',
        'igv', 'precios_incluyen_igv', 'moneda', 'simbolo_moneda', 'serie_boleta', 'serie_factura', 'serie_ticket',
        'configuracion_version',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_vencimiento' => 'date',
        'precios_incluyen_igv' => 'boolean',
        'igv' => 'decimal:2',
        'redes_sociales' => 'array',
        'publicada' => 'boolean',
        'horarios' => 'array',
        'condiciones_venta' => 'array',
        'configuracion_version' => 'integer',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function usuarios()
    {
        return $this->hasMany(User::class);
    }

    /** @return array<string, mixed> */
    public function publicContactData(): array
    {
        return [
            'nombre' => $this->nombre,
            'ruc' => $this->ruc,
            'email' => $this->email,
            'telefono' => $this->telefono,
            'whatsapp' => $this->whatsapp,
            'direccion' => $this->direccion,
            'ubicacion' => [
                'pais_codigo' => $this->pais_codigo,
                'pais' => StoreLocation::COUNTRIES[$this->pais_codigo] ?? null,
                'provincia' => $this->provincia,
                'ciudad' => $this->ciudad,
            ],
            'sitio_web' => $this->sitio_web,
            'redes_sociales' => $this->redes_sociales ?? [],
            'logo' => $this->logo,
            'descripcion' => $this->descripcion_publica,
            'color_primario' => $this->color_primario,
            'horarios' => $this->horarios ?? [],
            'condiciones_venta' => $this->condiciones_venta ?? [],
        ];
    }

    public function publicUrl(): string
    {
        return route('storefront.show', ['slug' => $this->slug]);
    }

    public function estaPublicada(): bool
    {
        return $this->publicada && $this->estaActiva();
    }

    // --- Estado de la suscripción -------------------------------------

    public function estaActiva(): bool
    {
        if ($this->estado === 'suspendida') {
            return false;
        }
        if ($this->fecha_vencimiento && $this->fecha_vencimiento->isPast()) {
            return false;
        }

        return true;
    }

    public function diasRestantes(): ?int
    {
        if (! $this->fecha_vencimiento) {
            return null;
        }

        return max(0, Carbon::today()->diffInDays($this->fecha_vencimiento, false));
    }

    public function estadoBadge(): array
    {
        return match ($this->estado) {
            'activa' => ['Activa', '#16a34a', '#dcfce7'],
            'prueba' => ['Prueba', '#c2410c', '#ffedd5'],
            'suspendida' => ['Suspendida', '#dc2626', '#fee2e2'],
            default => ['—', '#6b7280', '#f3f4f6'],
        };
    }

    // --- Límites del plan ---------------------------------------------

    /**
     * ¿La tienda puede agregar un registro más del recurso indicado?
     * $recurso: 'productos' | 'usuarios' | 'ventas_mes'
     */
    public function puedeAgregar(string $recurso): bool
    {
        if (! $this->plan) {
            return true;
        }
        $map = [
            'productos' => ['max_productos', fn () => Producto::where('tienda_id', $this->id)->where('activo', true)->count()],
            'usuarios' => ['max_usuarios', fn () => User::where('tienda_id', $this->id)->count()],
            'ventas_mes' => ['max_ventas_mes', fn () => Venta::where('tienda_id', $this->id)
                ->whereMonth('fecha', now()->month)
                ->whereYear('fecha', now()->year)->count()],
        ];
        if (! isset($map[$recurso])) {
            return true;
        }
        [$campo, $contador] = $map[$recurso];
        $limite = (int) $this->plan->{$campo};
        if ($limite === -1) {
            return true; // ilimitado
        }

        return $contador() < $limite;
    }
}
