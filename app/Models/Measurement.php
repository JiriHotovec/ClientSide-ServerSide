<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['device_id', 'measurement_type_id', 'value', 'measured_at', 'source'])]
class Measurement extends Model
{
    protected function casts(): array
    {
        return ['value' => 'float', 'measured_at' => 'datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(MeasurementType::class, 'measurement_type_id');
    }
}
