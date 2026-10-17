<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Database\Factories\MeasurementTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'name', 'unit', 'description', 'unit_position', 'unit_space', 'decimals', 'color', 'chart_type', 'y_min', 'y_max'])]
class MeasurementType extends Model
{
    /** @use HasFactory<MeasurementTypeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_public' => 'boolean', 'unit_space' => 'boolean', 'decimals' => 'integer', 'y_min' => 'float', 'y_max' => 'float'];
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Global types are visible to everyone; private ones only to their creator (and admins). */
    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, ?User $user): \Illuminate\Database\Eloquent\Builder
    {
        if ($user?->isAdmin()) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('is_public', true)->when($user, fn ($q) => $q->orWhere('created_by', $user->id)));
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
