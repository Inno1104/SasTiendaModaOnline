<?php

namespace App\Services;

use Illuminate\Support\Str;

class BrandNameNormalizer
{
    public function normalize(string $name): string
    {
        return Str::lower(Str::squish($name));
    }
}
