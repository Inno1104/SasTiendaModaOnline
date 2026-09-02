<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Aísla los datos por tienda (multi-tenant).
 *
 * - Al consultar: filtra automáticamente por la tienda del usuario autenticado.
 * - Al crear: asigna automáticamente la tienda del usuario autenticado.
 *
 * Los super-administradores NO son filtrados (ven todas las tiendas).
 */
trait PerteneceTienda
{
    public static function bootPerteneceTienda(): void
    {
        // Filtro global por tienda
        static::addGlobalScope('tienda', function (Builder $builder) {
            $tiendaId = self::tiendaActualId();
            if ($tiendaId !== null) {
                $builder->where($builder->getModel()->getTable() . '.tienda_id', $tiendaId);
            }
        });

        // Asignar tienda al crear
        static::creating(function ($model) {
            if (empty($model->tienda_id)) {
                $tiendaId = self::tiendaActualId();
                if ($tiendaId !== null) {
                    $model->tienda_id = $tiendaId;
                }
            }
        });
    }

    /**
     * Devuelve el id de la tienda actual, o null si no debe aplicarse el filtro
     * (sin sesión, o usuario super-admin).
     */
    protected static function tiendaActualId(): ?int
    {
        if (!auth()->check()) {
            return null;
        }
        $user = auth()->user();
        if ($user->es_super_admin) {
            return null;
        }
        return $user->tienda_id ?: null;
    }

    public function tienda()
    {
        return $this->belongsTo(\App\Models\Tienda::class);
    }
}
