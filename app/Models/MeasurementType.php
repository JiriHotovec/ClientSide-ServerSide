<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Database\Factories\MeasurementTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'name', 'unit', 'description'])]
class MeasurementType extends Model
{
    /** @use HasFactory<MeasurementTypeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'device_measurement_type');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    public function isInUse(): bool
    {
        return $this->devices()->exists() || $this->measurements()->exists();
    }
}
