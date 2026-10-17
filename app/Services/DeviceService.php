<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;

class DeviceService
{
    public function create(User $owner, array $data): Device
    {
        $device = new Device($this->attributes($data));
        $device->user_id = $owner->id;
        $device->is_public = (bool) ($data['is_public'] ?? false);
        $device->save();
        $device->types()->sync($data['types'] ?? []);

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
            $device->types()->sync($data['types']);
        }

        return $device;
    }

    public function setVisibility(Device $device, bool $public): Device
    {
        $device->is_public = $public;
        $device->save();

        return $device;
    }

    private function attributes(array $data): array
    {
        $attrs = array_intersect_key($data, array_flip(['name', 'description', 'location', 'source_url', 'source_mapping', 'fetch_interval_min']));
        if (isset($attrs['source_mapping']) && is_string($attrs['source_mapping'])) {
            $attrs['source_mapping'] = json_decode($attrs['source_mapping'], true);
        }

        return $attrs;
    }
}
