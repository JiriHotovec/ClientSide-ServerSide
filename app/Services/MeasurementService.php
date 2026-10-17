<?php

namespace App\Services;

use App\Models\Device;
use App\Models\MeasurementType;
use App\Support\DisplaySettings;
use App\Support\TimeRange;

class MeasurementService
{
    public const PERIODS = TimeRange::PRESETS;

    /** Aggregation bucket sizes in seconds (UTC aligned). */
    public const BUCKETS = ['hour' => 3600, '6h' => 21600, 'day' => 86400];

    /**
     * Single filtered dataset used by both the chart and the table (A3).
     * Rows with a missing value keep value = null; empty periods return no rows (never zeros).
     */
    public function series(Device $device, MeasurementType $type, string|TimeRange $period): array
    {
        $range = $period instanceof TimeRange ? $period : TimeRange::preset($period);
        [$from, $to] = [$range->from, $range->to];

        $rows = $device->measurements()
            ->where('measurement_type_id', $type->id)
            ->whereBetween('measured_at', [$from, $to])
            ->orderBy('measured_at')
            ->get(['measured_at', 'value', 'source']);

        $display = DisplaySettings::for($type);
        $data = $rows->map(fn ($m) => [
            'measured_at' => $m->measured_at->utc()->toIso8601String(),
            'value' => $m->value,
            'unit' => $display['unit'],
            'source' => $m->source,
        ])->values();

        $latest = $data->reverse()->first(fn ($r) => $r['value'] !== null);

        return [
            'device' => ['id' => $device->uuid, 'name' => $device->name, 'location' => $device->location],
            'type' => ['key' => $type->key, 'name' => $type->name, 'unit' => $display['unit'], 'category' => $type->category],
            'display' => $display,
            'period' => $range->key,
            'from' => $from->utc()->toIso8601String(),
            'to' => $to->utc()->toIso8601String(),
            'source_status' => [
                'ok' => $device->last_error === null,
                'message' => $device->lastErrorText(),
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

        return $type ? $this->snapshotFor($device, $type) : null;
    }

    /** Latest value, 24h min/max and sparkline of one dataset (dashboard tiles, cards). */
    public function snapshotFor(Device $device, MeasurementType $type): array
    {
        $rows = $device->measurements()->where('measurement_type_id', $type->id)
            ->where('measured_at', '>=', now()->subDay())->whereNotNull('value')->orderBy('measured_at')->get(['value', 'measured_at']);
        if ($rows->isEmpty()) {
            return ['type' => $type, 'display' => DisplaySettings::for($type), 'latest' => null, 'at' => null, 'points' => null];
        }
        $min = $rows->min('value');
        $max = $rows->max('value');
        $span = max($max - $min, 0.0001);
        $t0 = $rows->first()->measured_at->timestamp;
        $dt = max($rows->last()->measured_at->timestamp - $t0, 1);
        $points = $rows->map(fn ($r) => round(($r->measured_at->timestamp - $t0) / $dt * 100, 1).','.round(28 - ($r->value - $min) / $span * 26, 1))->implode(' ');

        return ['type' => $type, 'display' => DisplaySettings::for($type), 'formatted' => DisplaySettings::format($rows->last()->value, DisplaySettings::for($type)), 'min' => $min, 'max' => $max, 'latest' => $rows->last()->value, 'at' => $rows->last()->measured_at, 'points' => $points];
    }

    /**
     * Aggregates one dataset into time buckets (min / average / max). Missing values are ignored and a bucket
     * without any valid value stays empty instead of becoming zero.
     *
     * @return array{rows: list<array>, total: array, bucket: string, display: array}
     */
    public function aggregate(Device $device, MeasurementType $type, string|TimeRange $period, string $bucket, int $tzOffset = 0): array
    {
        $size = self::BUCKETS[$bucket];
        $range = $period instanceof TimeRange ? $period : TimeRange::preset($period);
        [$from, $to] = [$range->from, $range->to];
        $display = DisplaySettings::for($type);
        $shift = max(-840, min(840, $tzOffset)) * 60; // buckets start at the viewer's local hour/day

        $rows = $device->measurements()->where('measurement_type_id', $type->id)
            ->whereBetween('measured_at', [$from, $to])->orderBy('measured_at')->get(['measured_at', 'value']);

        $buckets = $rows->groupBy(fn ($m) => intdiv($m->measured_at->timestamp - $shift, $size) * $size + $shift)->map(function ($group, $start) use ($size) {
            $vals = $group->pluck('value')->filter(fn ($v) => $v !== null);

            return [
                'start' => \Illuminate\Support\Carbon::createFromTimestampUTC((int) $start),
                'min' => $vals->min(), 'avg' => $vals->isEmpty() ? null : $vals->avg(), 'max' => $vals->max(),
                'count' => $vals->count(), 'missing' => $group->count() - $vals->count(),
            ];
        })->sortKeysDesc()->values()->all(); // newest first

        $all = $rows->pluck('value')->filter(fn ($v) => $v !== null);

        return [
            'rows' => $buckets,
            'total' => ['min' => $all->min(), 'avg' => $all->isEmpty() ? null : $all->avg(), 'max' => $all->max(), 'count' => $all->count(), 'missing' => $rows->count() - $all->count()],
            'bucket' => $bucket,
            'display' => $display,
        ];
    }
}
