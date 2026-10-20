<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
use App\Support\DisplaySettings;

class DeviceService
{
    public function create(User $owner, array $data): Device
    {
        $device = new Device($this->attributes($data));
        $device->user_id = $owner->id;
        $device->is_public = (bool) ($data['is_public'] ?? false);
        $device->save();
        $this->syncTypes($device, $data['types'] ?? [], $data['type_settings'] ?? []);
        $this->syncCharts($device, $data['charts'] ?? []);

        return $device;
    }

    public function update(Device $device, array $data): Device
    {
        $device->fill($this->attributes($data));
        if (array_key_exists('is_public', $data)) {
            $device->is_public = (bool) $data['is_public'];
        }
        $device->save();
        if (array_key_exists('types', $data)) {
            $this->syncTypes($device, $data['types'], $data['type_settings'] ?? []);
        }
        if (array_key_exists('charts', $data)) {
            $this->syncCharts($device, $data['charts']);
        }

        return $device;
    }

    public function setVisibility(Device $device, bool $public): Device
    {
        $device->is_public = $public;
        $device->save();

        return $device;
    }

    /** @param list<int> $ids  @param array<int,array> $settings per-device display overrides keyed by type id */
    private function syncTypes(Device $device, array $ids, array $settings): void
    {
        $sync = [];
        foreach ($ids as $id) {
            $row = array_intersect_key($settings[$id] ?? [], array_flip(DisplaySettings::FIELDS));
            $sync[$id] = array_map(fn ($v) => $v === '' ? null : $v, $row) + array_fill_keys(DisplaySettings::FIELDS, null);
        }
        $device->types()->sync($sync);
        $device->charts()->whereNotIn('measurement_type_id', $ids)->delete(); // charts of removed datasets go away too
    }

    /**
     * Replaces the arranged charts; the order of the list is the display order.
     *
     * @param  list<array{type: int, kind: string, title: ?string}>  $charts
     */
    private function syncCharts(Device $device, array $charts): void
    {
        $device->charts()->delete();
        foreach (array_values($charts) as $position => $chart) {
            $device->charts()->create([
                'measurement_type_id' => $chart['type'], 'kind' => $chart['kind'], 'title' => $chart['title'] ?? null, 'position' => $position,
            ]);
        }
    }

    private function attributes(array $data): array
    {
        $attrs = array_intersect_key($data, array_flip(['name', 'description', 'location', 'latitude', 'longitude', 'source_url', 'source_mapping', 'fetch_interval_min']));
        if (isset($attrs['source_mapping']) && is_string($attrs['source_mapping'])) {
            $attrs['source_mapping'] = json_decode($attrs['source_mapping'], true);
        }

        return $attrs;
    }
}
