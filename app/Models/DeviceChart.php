<?php

namespace App\Models;

use Database\Factories\DeviceChartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['measurement_type_id', 'kind', 'title', 'position'])]
class DeviceChart extends Model
{
    /** @use HasFactory<DeviceChartFactory> */
    use HasFactory;

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @return BelongsTo<MeasurementType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(MeasurementType::class, 'measurement_type_id');
    }
}
