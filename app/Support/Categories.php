<?php

namespace App\Support;

/** Logical groups of measurement types; they become the tabs of a device dashboard. Order = tab order. */
class Categories
{
    public const ALL = [
        'weather' => 'Weather',
        'air' => 'Air quality',
        'water' => 'Water and soil',
        'energy' => 'Energy',
        'other' => 'Other',
    ];

    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    public static function label(string $key): string
    {
        return __(self::ALL[$key] ?? self::ALL['other']);
    }
}
