<?php

namespace App\Models;

use App\Models\Concerns\PerteneceTienda;
use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    use PerteneceTienda;

    protected $fillable = ['tienda_id', 'user_id', 'accion', 'auditable_type', 'auditable_id', 'descripcion', 'metadata', 'ip', 'user_agent'];
    protected $casts = ['metadata' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
