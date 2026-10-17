<?php

namespace App\Policies;

use App\Models\MeasurementType;
use App\Models\User;

class MeasurementTypePolicy
{
    public function update(User $user, MeasurementType $type): bool
    {
        return ! $type->is_system && ($user->isAdmin() || $type->created_by === $user->id);
    }

    public function delete(User $user, MeasurementType $type): bool
    {
        return $this->update($user, $type);
    }
}
