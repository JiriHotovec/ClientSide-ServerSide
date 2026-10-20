<?php

namespace App\Support;

/** Chart styles a dataset can be shown with. Labels are translation keys. */
class ChartKinds
{
    public const ALL = [
        'line' => 'Line',
        'area' => 'Area',
        'step' => 'Step',
        'bar' => 'Bars',
        'scatter' => 'Points',
        'gauge' => 'Gauge',
        'rose' => 'Wind rose',
    ];

    public const MAX_PER_DEVICE = 24;

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    public static function label(string $kind): string
    {
        return __(self::ALL[$kind] ?? self::ALL['line']);
    }
}
