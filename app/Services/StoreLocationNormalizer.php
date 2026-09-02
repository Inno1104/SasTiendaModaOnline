<?php

namespace App\Services;

use Illuminate\Support\Str;

final class StoreLocationNormalizer
{
    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalize(array $data): array
    {
        foreach (['provincia', 'ciudad'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->nullableText($data[$field]);
            }
        }
        if (array_key_exists('pais_codigo', $data)) {
            $country = $this->nullableText($data['pais_codigo']);
            $data['pais_codigo'] = $country === null ? null : mb_strtoupper($country);
        }

        return $data;
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return $value === null ? null : (string) $value;
        }

        $value = Str::squish($value);

        return $value === '' ? null : $value;
    }
}
