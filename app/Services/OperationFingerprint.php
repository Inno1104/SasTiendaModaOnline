<?php

namespace App\Services;

class OperationFingerprint
{
    /** @param array<string, mixed> $payload */
    public function make(array $payload): string
    {
        return hash('sha256', json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => $this->canonicalize($item), $value);
    }
}
