<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** A time window: a preset (last 24 hours / 7 days) or a custom date range (UTC). */
final class TimeRange
{
    public const PRESETS = ['24h' => 24, '7d' => 168];
    public const MAX_DAYS = 92;

    public function __construct(public readonly Carbon $from, public readonly Carbon $to, public readonly string $key) {}

    public static function preset(string $period): self
    {
        $to = now();

        return new self($to->copy()->subHours(self::PRESETS[$period]), $to, $period);
    }

    /**
     * Custom range. $tz is the viewer's offset in minutes as reported by JavaScript's getTimezoneOffset()
     * (UTC minus local, e.g. -120 for CEST); it applies to values without an explicit zone.
     * Date-only values cover whole days, "2026-09-11T08:30" is a local time to the minute, "...Z" is used as given.
     */
    public static function custom(string $from, string $to, int $tz = 0): self
    {
        return new self(self::parse($from, false, $tz), self::parse($to, true, $tz), 'custom');
    }

    /** Custom range when both dates are present, otherwise the preset (default 24h). */
    public static function fromInput(?string $period, ?string $from, ?string $to, int $tz = 0): self
    {
        if (filled($from) && filled($to)) {
            return self::custom($from, $to, $tz);
        }

        return self::preset(array_key_exists((string) $period, self::PRESETS) ? $period : '24h');
    }

    public function days(): float
    {
        return $this->from->diffInSeconds($this->to) / 86400;
    }

    /** Sensible aggregation for the popup table. */
    public function defaultBucket(): string
    {
        return $this->days() <= 2 ? 'hour' : ($this->days() <= 14 ? '6h' : 'day');
    }

    private static function parse(string $value, bool $end, int $tz): Carbon
    {
        $tz = max(-840, min(840, $tz));

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $c = Carbon::parse($value, 'UTC')->utc();

            return ($end ? $c->endOfDay() : $c->startOfDay())->addMinutes($tz);
        }
        if (preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $value)) {
            return Carbon::parse($value)->utc(); // explicit zone: use as given
        }
        $c = Carbon::parse($value, 'UTC')->utc();
        if ($end && preg_match('/T\d{2}:\d{2}$/', $value)) {
            $c = $c->endOfMinute();
        }

        return $c->addMinutes($tz);
    }
}
