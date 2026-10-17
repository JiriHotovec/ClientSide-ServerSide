<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    /** Public devices are visible to everyone (guests included); private ones only to owner/admin. */
    public function view(?User $user, Device $device): bool
    {
        return $device->is_public || $this->manage($user, $device);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function manage(?User $user, Device $device): bool
    {
        return $user !== null && ($user->isAdmin() || $device->user_id === $user->id);
    }

    public function update(User $user, Device $device): bool
    {
        return $this->manage($user, $device);
    }

    public function delete(User $user, Device $device): bool
    {
        return $this->manage($user, $device);
    }
}
