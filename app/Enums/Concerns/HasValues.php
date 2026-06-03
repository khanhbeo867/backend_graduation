<?php

namespace App\Enums\Concerns;

trait HasValues
{
    public static function values(): array
    {
        return array_column(static::cases(), 'value');
    }
}
