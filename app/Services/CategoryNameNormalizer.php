<?php

namespace App\Services;

use Illuminate\Support\Str;

class CategoryNameNormalizer
{
    public function normalize(string $name): string
    {
        return Str::lower(Str::squish($name));
    }
}
