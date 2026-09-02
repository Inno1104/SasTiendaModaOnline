<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['rol_id', 'tienda_id', 'name', 'email', 'password', 'telefono', 'avatar', 'activo', 'es_super_admin'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed', 'activo' => 'boolean', 'es_super_admin' => 'boolean'];

    public function rol()
    {
        return $this->belongsTo(Rol::class);
    }

    public function tienda()
    {
        return $this->belongsTo(Tienda::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function isAdmin(): bool
    {
        return mb_strtolower(trim((string) $this->rol?->nombre))
            === mb_strtolower(Rol::ADMINISTRATOR);
    }

    public function esSuperAdmin()
    {
        return (bool) $this->es_super_admin;
    }
}
