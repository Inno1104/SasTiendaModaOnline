<?php

namespace App\Services;

use App\Models\Tienda;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Session\Store as SessionStore;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use JsonException;

final class GuestCheckoutManager
{
    /** @param array<string,mixed> $validated */
    public function save(Tienda $store, SessionStore $session, array $validated): void
    {
        $payload = [
            'cliente' => $validated['cliente'],
            'entrega' => $validated['entrega'],
            'fiscal' => $validated['fiscal'],
            'updated_at' => now()->toIso8601String(),
        ];

        $session->put(
            $this->sessionKey($store),
            Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
        );
    }

    /** @return array<string,mixed> */
    public function snapshot(Tienda $store, SessionStore $session): array
    {
        $encrypted = $session->get($this->sessionKey($store));
        if (! is_string($encrypted) || $encrypted === '') {
            return $this->emptySnapshot($store);
        }

        try {
            $decoded = json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            $session->forget($this->sessionKey($store));

            return $this->emptySnapshot($store);
        }

        if (! is_array($decoded)
            || ! is_array($decoded['cliente'] ?? null)
            || ! is_array($decoded['entrega'] ?? null)
            || ! is_array($decoded['fiscal'] ?? null)) {
            $session->forget($this->sessionKey($store));

            return $this->emptySnapshot($store);
        }

        return $decoded;
    }

    public function clear(Tienda $store, SessionStore $session): void
    {
        $session->forget($this->sessionKey($store));
    }

    public function orderOperationId(Tienda $store, SessionStore $session): string
    {
        $key = $this->operationKey($store);
        $operationId = $session->get($key);
        if (! is_string($operationId) || ! Str::isUuid($operationId)) {
            $operationId = (string) Str::uuid();
            $session->put($key, $operationId);
        }

        return $operationId;
    }

    public function rotateOrderOperation(Tienda $store, SessionStore $session): void
    {
        $session->put($this->operationKey($store), (string) Str::uuid());
    }

    private function sessionKey(Tienda $store): string
    {
        return 'guest_checkout_store_'.$store->id;
    }

    private function operationKey(Tienda $store): string
    {
        return 'online_order_operation_store_'.$store->id;
    }

    /** @return array<string,mixed> */
    private function emptySnapshot(Tienda $store): array
    {
        return [
            'cliente' => ['nombre' => null, 'apellido' => null, 'dni' => null, 'email' => null, 'telefono' => null],
            'entrega' => [
                'pais_codigo' => $store->pais_codigo ?: 'EC',
                'provincia' => null,
                'ciudad' => null,
                'codigo_postal' => null,
                'direccion' => null,
                'referencia' => null,
            ],
            'fiscal' => [
                'requiere_factura' => false,
                'tipo_comprobante' => 'boleta',
                'usar_datos_contacto' => true,
                'tipo_identificacion' => null,
                'identificacion' => null,
                'razon_social' => null,
                'direccion_fiscal' => null,
            ],
            'updated_at' => null,
        ];
    }
}
