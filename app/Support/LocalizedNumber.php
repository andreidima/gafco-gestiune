<?php

namespace App\Support;

final class LocalizedNumber
{
    public static function quantity(mixed $value, int $precision = 3): string
    {
        return rtrim(rtrim(number_format((float) $value, $precision, ',', '.'), '0'), ',');
    }
}
