<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function record(Model $model, string $action, string $description, ?User $user, array $metadata = []): Auditoria
    {
        return Auditoria::create([
            'tienda_id' => $model->getAttribute('tienda_id') ?: $user?->tienda_id,
            'user_id' => $user?->id,
            'accion' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'descripcion' => $description,
            'metadata' => $metadata ?: null,
            'ip' => request()?->ip(),
            'user_agent' => mb_substr((string) request()?->userAgent(), 0, 500) ?: null,
        ]);
    }
}
