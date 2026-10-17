<?php

namespace App\Support;

use App\Models\MeasurementType;

/** Resolves how a dataset is displayed: type defaults, overridden per device (pivot) where set. */
class DisplaySettings
{
    public const FIELDS = ['unit', 'unit_position', 'unit_space', 'decimals', 'color', 'chart_type', 'y_min', 'y_max'];

    public static function for(MeasurementType $type): array
    {
        $pivot = $type->pivot?->only(self::FIELDS) ?? [];
        $out = [];
        foreach (self::FIELDS as $f) {
            $out[$f] = $pivot[$f] ?? $type->{$f};
        }
        $out['unit_space'] = (bool) $out['unit_space'];
        $out['decimals'] = (int) $out['decimals'];
        $out['color'] ??= '#0077b6';

        return $out;
    }

    /** 21.456 + {unit:"°C", position:after, space, decimals:1} => "21.5 °C" */
    public static function format(?float $value, array $d, bool $withUnit = true): string
    {
        if ($value === null) {
            return 'Missing value';
        }
        $n = number_format($value, $d['decimals'], '.', '');
        if (! $withUnit || $d['unit_position'] === 'none' || ($d['unit'] ?? '') === '') {
            return $n;
        }
        $sp = $d['unit_space'] ? "\u{00A0}" : '';

        return $d['unit_position'] === 'before' ? $d['unit'].$sp.$n : $n.$sp.$d['unit'];
    }
}
