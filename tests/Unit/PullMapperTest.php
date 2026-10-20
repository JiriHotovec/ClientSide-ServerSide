<?php

use App\Services\PullService;

beforeEach(fn () => $this->svc = app(PullService::class));

it('maps dot paths of a single object', function () {
    $out = $this->svc->map(['timestamp' => 'c.t', 'values' => ['temperature' => 'c.temp', 'humidity' => 'c.hum']], ['c' => ['t' => '2026-10-17T10:00:00+02:00', 'temp' => '21.5', 'hum' => 40]]);

    expect($out)->toHaveCount(2)
        ->and($out[0]['value'])->toBe(21.5)
        ->and($out[0]['measured_at']->toIso8601String())->toBe('2026-10-17T08:00:00+00:00');
});

it('maps absent or non-numeric values to null, never zero', function () {
    $out = $this->svc->map(['values' => ['a' => 'x', 'b' => 'y', 'c' => 'z']], ['y' => 'n/a', 'z' => null]);

    expect(array_column($out, 'value'))->toBe([null, null, null]);
});

it('falls back to the current minute without a timestamp path', function () {
    $out = $this->svc->map(['values' => ['a' => 'v']], ['v' => 1]);

    expect($out[0]['measured_at']->second)->toBe(0);
});

it('returns nothing for an empty mapping', function () {
    expect($this->svc->map([], ['a' => 1]))->toBe([]);
});
