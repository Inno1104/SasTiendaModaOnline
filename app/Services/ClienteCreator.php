<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Support\Facades\DB;

class ClienteCreator
{
    /** @param array<string, mixed> $data */
    public function create(array $data, int $tiendaId): Cliente
    {
        return DB::transaction(function () use ($data, $tiendaId): Cliente {
            DB::table('cliente_contadores')->insertOrIgnore([
                'tienda_id' => $tiendaId,
                'ultimo_numero' => $this->lastExistingNumber($tiendaId),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counter = DB::table('cliente_contadores')
                ->where('tienda_id', $tiendaId)
                ->lockForUpdate()
                ->first();

            if ($counter === null) {
                throw new \RuntimeException('No se pudo inicializar el consecutivo del cliente.');
            }

            $nextNumber = (int) $counter->ultimo_numero + 1;

            DB::table('cliente_contadores')->where('tienda_id', $tiendaId)->update([
                'ultimo_numero' => $nextNumber,
                'updated_at' => now(),
            ]);

            return Cliente::create(array_merge($data, [
                'tienda_id' => $tiendaId,
                'codigo' => 'CLI-'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT),
                'activo' => true,
            ]));
        }, 5);
    }

    private function lastExistingNumber(int $tiendaId): int
    {
        return Cliente::withoutGlobalScopes()
            ->where('tienda_id', $tiendaId)
            ->pluck('codigo')
            ->reduce(function (int $highest, string $code): int {
                return preg_match('/^CLI-(\d+)$/', $code, $matches)
                    ? max($highest, (int) $matches[1])
                    : $highest;
            }, 0);
    }
}
