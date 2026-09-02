<?php

namespace App\Services;

use Illuminate\Support\Str;

final class StoreIdentityNormalizer
{
    public function __construct(private readonly StoreLocationNormalizer $locations) {}

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalize(array $data): array
    {
        foreach (['nombre', 'direccion'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->nullableText($data[$field]);
            }
        }

        if (array_key_exists('email', $data)) {
            $email = $this->nullableText($data['email']);
            $data['email'] = $email === null ? null : mb_strtolower($email);
        }
        if (array_key_exists('ruc', $data)) {
            $taxId = $this->nullableText($data['ruc']);
            $data['ruc'] = $taxId === null
                ? null
                : mb_strtoupper((string) preg_replace('/[\s.]+/u', '', $taxId));
        }
        foreach (['telefono', 'whatsapp'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->phone($data[$field]);
            }
        }
        if (array_key_exists('sitio_web', $data)) {
            $data['sitio_web'] = $this->nullableText($data['sitio_web']);
        }
        if (array_key_exists('redes_sociales', $data) && is_array($data['redes_sociales'])) {
            $socialNetworks = $data['redes_sociales'];
            foreach (['instagram', 'facebook', 'tiktok'] as $network) {
                $url = $this->nullableText($data['redes_sociales'][$network] ?? null);
                if ($url === null) {
                    unset($socialNetworks[$network]);
                } else {
                    $socialNetworks[$network] = $url;
                }
            }
            $data['redes_sociales'] = $socialNetworks === [] ? null : $socialNetworks;
        }

        return $this->locations->normalize($data);
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return $value === null ? null : (string) $value;
        }

        $value = Str::squish($value);

        return $value === '' ? null : $value;
    }

    private function phone(mixed $value): ?string
    {
        $phone = $this->nullableText($value);
        if ($phone === null) {
            return null;
        }

        if (! preg_match('/^[+\d\s().-]+$/', $phone)) {
            return $phone;
        }

        $prefix = str_starts_with($phone, '+') ? '+' : '';

        return $prefix.preg_replace('/\D+/', '', $phone);
    }
}
