<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    public const ADMINISTRATOR = 'Administrador';

    public const ASSIGNABLE_NAMES = [self::ADMINISTRATOR, 'Vendedor', 'Almacén'];

    protected $table = 'roles';

    protected $fillable = ['nombre', 'descripcion'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
