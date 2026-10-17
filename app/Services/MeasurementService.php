<?php

namespace App\Services;

use App\Models\Device;
use App\Models\MeasurementType;

class MeasurementService
{
    public const PERIODS = ['24h' => 24, '7d' => 168];

    /**
     * Single filtered dataset used by both the chart and the table (A3).
     * Rows with a missing value keep value = null; empty periods return no rows (never zeros).
     */
    public function series(Device $device, MeasurementType $type, string $period): array
    {
        $hours = self::PERIODS[$period];
        $to = now();
        $from = $to->copy()->subHours($hours);

        $rows = $device->measurements()
            ->where('measurement_type_id', $type->id)
            ->whereBetween('measured_at', [$from, $to])
            ->orderBy('measured_at')
            ->get(['measured_at', 'value', 'source']);

        $data = $rows->map(fn ($m) => [
            'measured_at' => $m->measured_at->utc()->toIso8601String(),
            'value' => $m->value,
            'unit' => $type->unit,
            'source' => $m->source,
        ])->values();

        $latest = $data->reverse()->first(fn ($r) => $r['value'] !== null);

        return [
            'device' => ['id' => $device->id, 'name' => $device->name, 'location' => $device->location],
            'type' => ['key' => $type->key, 'name' => $type->name, 'unit' => $type->unit],
            'period' => $period,
            'from' => $from->utc()->toIso8601String(),
            'to' => $to->utc()->toIso8601String(),
            'source_status' => [
                'ok' => $device->last_error === null,
                'message' => $device->last_error,
                'last_fetched_at' => $device->last_fetched_at?->utc()->toIso8601String(),
            ],
            'summary' => [
                'count' => $data->count(),
                'missing' => $data->whereNull('value')->count(),
                'latest' => $latest,
            ],
            'data' => $data,
        ];
    }

    /**
     * Small snapshot for cards/hero: latest value of the device's first dataset plus a 24h sparkline
     * (SVG polyline points in a 100x30 box). Missing values are skipped, never drawn as zero.
     */
    public function snapshot(Device $device): ?array
    {
        $type = $device->types->first();
        if (! $type) {
            return null;
        }
        $rows = $device->measurements()->where('measurement_type_id', $type->id)
            ->where('measured_at', '>=', now()->subDay())->whereNotNull('value')->orderBy('measured_at')->get(['value', 'measured_at']);
        if ($rows->isEmpty()) {
            return ['type' => $type, 'latest' => null, 'at' => null, 'points' => null];
        }
        $min = $rows->min('value');
        $max = $rows->max('value');
        $span = max($max - $min, 0.0001);
        $t0 = $rows->first()->measured_at->timestamp;
        $dt = max($rows->last()->measured_at->timestamp - $t0, 1);
        $points = $rows->map(fn ($r) => round(($r->measured_at->timestamp - $t0) / $dt * 100, 1).','.round(28 - ($r->value - $min) / $span * 26, 1))->implode(' ');

        return ['type' => $type, 'latest' => $rows->last()->value, 'at' => $rows->last()->measured_at, 'points' => $points];
    }
}
