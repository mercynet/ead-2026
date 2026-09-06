<?php

namespace App\Modules\Learning\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

class CategoryNameNormalizer
{
    public function normalize(string $name): string
    {
        return (string) $this->normalizedString($name);
    }

    private function normalizedString(string $name): Stringable
    {
        return Str::of($name)->ascii()->lower()->squish();
    }
}
