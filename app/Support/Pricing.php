<?php

namespace App\Support;

class Pricing
{
    public static function for(string $type, string $tier): ?int
    {
        $value = config("pricing.{$type}.{$tier}");

        return is_int($value) ? $value : null;
    }
}
