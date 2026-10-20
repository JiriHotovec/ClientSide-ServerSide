<?php

use App\Models\Measurement;
use App\Support\TimeRange;

function at($device, $type, $value, $when)
{
    return Measurement::create(['device_id' => $device->id, 'measurement_type_id' => $type->id, 'value' => $value, 'measured_at' => $when, 'source' => 'seed']);
}

it('returns only measurements inside a custom date range (whole days, UTC)', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    at($device, $type, 1, '2026-09-10 23:59:00');
    at($device, $type, 2, '2026-09-11 00:00:00');
    at($device, $type, 3, '2026-09-12 23:59:59');
    at($device, $type, 4, '2026-09-13 00:00:00');

    $res = $this->getJson("/api/public/devices/{$device->uuid}/measurements?from=2026-09-11&to=2026-09-12")->assertOk();

    expect(collect($res->json('data'))->pluck('value')->all())->toEqual([2, 3]);
    $res->assertJsonPath('period', 'custom')->assertJsonPath('summary.count', 2);
});

it('validates the custom range', function (string $query, string $field) {
    [$device] = deviceWithData(['is_public' => true]);

    $this->getJson("/api/public/devices/{$device->uuid}/measurements?{$query}")->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'only from' => ['from=2026-09-01', 'to'],
    'only to' => ['to=2026-09-01', 'from'],
    'reversed' => ['from=2026-09-10&to=2026-09-01', 'to'],
    'not a date' => ['from=abc&to=def', 'from'],
    'too long' => ['from=2026-01-01&to=2026-12-31', 'to'],
]);

it('keeps presets working and ignores empty custom fields', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    at($device, $type, 9, now()->subHour());

    $this->getJson("/api/public/devices/{$device->uuid}/measurements?period=24h&from=&to=")->assertOk()->assertJsonPath('period', '24h')->assertJsonPath('summary.count', 1);
});

it('applies custom ranges to private devices only for authorized users', function () {
    [$device] = deviceWithData(['is_public' => false]);

    $this->getJson("/api/public/devices/{$device->uuid}/measurements?from=2026-09-01&to=2026-09-02")->assertNotFound();
    $this->getJson("/api/devices/{$device->uuid}/measurements?from=2026-09-01&to=2026-09-02")->assertUnauthorized();
});

it('aggregates a custom range in the popup and offers the date inputs', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    at($device, $type, 10, '2026-09-11 10:00:00');
    at($device, $type, 30, '2026-09-11 14:00:00');

    $res = $this->get("/en/devices/{$device->uuid}/table/{$type->key}?period=custom&from=2026-09-11&to=2026-09-11&bucket=day")->assertOk();

    $res->assertSee('name="from"', false)->assertSee('value="2026-09-11"', false)->assertSee('10.0')->assertSee('20.0')->assertSee('30.0')->assertSee('Custom dates');
});

it('defaults a custom popup to the last seven days and rejects bad dates', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    $base = "/en/devices/{$device->uuid}/table/{$type->key}";

    $this->get("$base?period=custom")->assertOk()->assertSee('name="from"', false);
    $this->get("$base?period=custom&from=2026-09-10&to=2026-09-01")->assertStatus(422);
    $this->get("$base?period=custom&from=2026-01-01&to=2026-12-31")->assertStatus(422);
    $this->get("$base?period=custom&from=garbage&to=2026-09-01")->assertStatus(422);
});

it('does not show date inputs for the preset periods in the popup', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);

    $this->get("/en/devices/{$device->uuid}/table/{$type->key}?period=7d")->assertDontSee('name="from"', false);
});

it('renders the custom range controls, the group-independent date form and the enlarge button', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);

    $html = $this->get("/en/devices/{$device->uuid}")->getContent();
    expect($html)->toContain('data-period="custom"')->toContain('data-range-form')->toContain('name="from"');

    $this->get("/en/devices/{$device->uuid}/table/{$type->key}")->assertSee('data-expand', false)->assertSee('Enlarge');
});

it('derives a sensible default bucket from the range length', function () {
    expect(TimeRange::custom('2026-09-01', '2026-09-02')->defaultBucket())->toBe('hour')
        ->and(TimeRange::custom('2026-09-01', '2026-09-10')->defaultBucket())->toBe('6h')
        ->and(TimeRange::custom('2026-09-01', '2026-10-30')->defaultBucket())->toBe('day');
});

it('treats date-only input as whole UTC days and full timestamps as given', function () {
    $r = TimeRange::custom('2026-09-11', '2026-09-11');
    $t = TimeRange::custom('2026-09-11T08:30:00Z', '2026-09-11T09:30:00Z');

    expect($r->from->toIso8601String())->toBe('2026-09-11T00:00:00+00:00')
        ->and($r->to->format('H:i:s'))->toBe('23:59:59')
        ->and($t->days())->toBeLessThan(0.05);
});

it('accepts local date and time and converts with the viewer timezone offset', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    // Prague in summer: JavaScript reports tz = -120, so local 10:00 = 08:00 UTC.
    at($device, $type, 1, '2026-09-11 07:59:00');
    at($device, $type, 2, '2026-09-11 08:00:00');
    at($device, $type, 3, '2026-09-11 08:59:00');
    at($device, $type, 4, '2026-09-11 09:01:00');

    $res = $this->getJson("/api/public/devices/{$device->uuid}/measurements?from=2026-09-11T10:00&to=2026-09-11T10:59&tz=-120")->assertOk();

    expect(collect($res->json('data'))->pluck('value')->all())->toEqual([2, 3]);
});

it('treats date-only values as whole local days when a timezone is given', function () {
    $r = TimeRange::custom('2026-09-11', '2026-09-11', -120);

    expect($r->from->toIso8601String())->toBe('2026-09-10T22:00:00+00:00')->and($r->to->format('Y-m-d H:i'))->toBe('2026-09-11 21:59');
});

it('makes the end time inclusive to the minute and respects explicit zones', function () {
    $r = TimeRange::custom('2026-09-11T08:00', '2026-09-11T08:30');
    $z = TimeRange::custom('2026-09-11T08:00:00+02:00', '2026-09-11T09:00:00+02:00', -300);

    expect($r->to->format('H:i:s'))->toBe('08:30:59')
        ->and($z->from->toIso8601String())->toBe('2026-09-11T06:00:00+00:00'); // tz ignored for explicit zones
});

it('rejects an out-of-range timezone offset', function () {
    [$device] = deviceWithData(['is_public' => true]);

    $this->getJson("/api/public/devices/{$device->uuid}/measurements?from=2026-09-01&to=2026-09-02&tz=9999")->assertUnprocessable()->assertJsonValidationErrors('tz');
    $this->get("/en/devices/{$device->uuid}/table/temperature?period=custom&tz=9999")->assertStatus(422);
});

it('shows datetime inputs in the popup and the panel, and day buckets start at local midnight', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    at($device, $type, 10, '2026-09-10 22:30:00'); // 00:30 local on the 11th (tz -120)
    at($device, $type, 30, '2026-09-11 21:00:00'); // 23:00 local on the 11th

    $res = $this->get("/en/devices/{$device->uuid}/table/{$type->key}?period=custom&from=2026-09-11T00:00&to=2026-09-11T23:59&bucket=day&tz=-120")->assertOk();
    $res->assertSee('type="datetime-local"', false)->assertSee('20.0')->assertSee('name="tz"', false);

    $this->get("/en/devices/{$device->uuid}")->assertSee('type="datetime-local"', false)->assertSee('name="tz"', false);
});
