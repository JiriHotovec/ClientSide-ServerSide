<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

// user_id, is_public and the token hash are assigned explicitly by services, never mass-assigned.
#[Fillable(['name', 'description', 'location', 'source_url', 'source_mapping', 'fetch_interval_min'])]
#[Hidden(['id', 'ingest_token_hash'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Device $device) {
            $device->uuid ??= (string) Str::uuid7();
        });
    }

    /** URLs and the REST API address devices by UUIDv7, never by the sequential database id. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'source_mapping' => 'array',
            'last_fetched_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function types(): BelongsToMany
    {
        return $this->belongsToMany(MeasurementType::class, 'device_measurement_type')->withPivot(\App\Support\DisplaySettings::FIELDS)->orderBy('name');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->isAdmin()) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('is_public', true)
            ->when($user, fn ($q) => $q->orWhere('user_id', $user->id)));
    }

    /** Stored errors are English keys (written by the cron job); shown in the viewer's language. */
    public function lastErrorText(): ?string
    {
        $e = $this->last_error;
        if ($e === null) {
            return null;
        }
        if (preg_match('/^Data source responded with HTTP (\d+)\.$/', $e, $m)) {
            return __('Data source responded with HTTP :status.', ['status' => $m[1]]);
        }

        return __($e);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Generates a new ingest token; returns the plain value (shown once, only the hash is stored). */
    public function regenerateIngestToken(): string
    {
        $plain = 'mv_'.Str::random(40);
        $this->forceFill(['ingest_token_hash' => self::hashToken($plain)])->save();

        return $plain;
    }
}
