<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Measurement;

class IngestService
{
    /**
     * Idempotent upsert of readings. Only types assigned to the device are accepted.
     *
     * @param  list<array{type:string,value:?float,measured_at:mixed}>  $readings
     */
    public function store(Device $device, array $readings, string $source): int
    {
        $typeIds = $device->types()->pluck('measurement_types.id', 'key');
        $rows = [];

        foreach ($readings as $r) {
            if (! isset($typeIds[$r['type']])) {
                continue;
            }
            $rows[] = [
                'device_id' => $device->id,
                'measurement_type_id' => $typeIds[$r['type']],
                'value' => $r['value'],
                'measured_at' => \Illuminate\Support\Carbon::parse($r['measured_at'])->utc()->toDateTimeString(),
                'source' => $source,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($rows) {
            Measurement::upsert($rows, ['device_id', 'measurement_type_id', 'measured_at'], ['value', 'source', 'updated_at']);
        }

        return count($rows);
    }
}
