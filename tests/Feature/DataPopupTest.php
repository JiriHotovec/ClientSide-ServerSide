<?php

use App\Models\Measurement;
use App\Models\User;

function reading($device, $type, $value, $when)
{
    return Measurement::create(['device_id' => $device->id, 'measurement_type_id' => $type->id, 'value' => $value, 'measured_at' => $when, 'source' => 'seed']);
}

it('aggregates readings into hourly lowest, average and highest values', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    $hour = now()->startOfHour()->subHours(2);
    reading($device, $type, 10, $hour->copy()->addMinutes(5));
    reading($device, $type, 20, $hour->copy()->addMinutes(35));
    reading($device, $type, 30, $hour->copy()->addHour()->addMinutes(10));

    $res = $this->get("/devices/{$device->uuid}/table/{$type->key}?period=24h&bucket=hour")->assertOk();

    $res->assertSee('Lowest')->assertSee('10.0')->assertSee('15.0')->assertSee('20.0')->assertSee('30.0')->assertDontSee('<html', false);
});

it('ignores missing values in averages and never shows zero for an empty bucket', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    $hour = now()->startOfHour()->subHours(3);
    reading($device, $type, null, $hour->copy()->addMinutes(5));          // bucket 1: only missing
    reading($device, $type, 8, $hour->copy()->addHour()->addMinutes(5));  // bucket 2
    reading($device, $type, null, $hour->copy()->addHour()->addMinutes(25));

    $res = $this->get("/devices/{$device->uuid}/table/{$type->key}?bucket=hour")->assertOk();

    $res->assertSee('No data')->assertSee('8.0')->assertSee('1 missing')->assertDontSee('0.0');
});

it('tells the user when the period has no measurements', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);

    $this->get("/devices/{$device->uuid}/table/{$type->key}")->assertOk()->assertSee('No measurements in this period');
});

it('groups by day and by six hours, and rejects invalid parameters', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    reading($device, $type, 5, now()->subHours(30));
    reading($device, $type, 15, now()->subHours(29));

    $this->get("/devices/{$device->uuid}/table/{$type->key}?period=7d&bucket=day")->assertOk()->assertSee('10.0');
    $this->get("/devices/{$device->uuid}/table/{$type->key}?period=7d&bucket=6h")->assertOk();
    $this->get("/devices/{$device->uuid}/table/{$type->key}?period=1y")->assertStatus(422);
    $this->get("/devices/{$device->uuid}/table/{$type->key}?bucket=minute")->assertStatus(422);
});

it('hides the popup data of private devices from outsiders but not from the owner or admins', function () {
    $owner = User::factory()->create();
    [$device, $type] = deviceWithData(['is_public' => false, 'user' => $owner]);
    $url = "/devices/{$device->uuid}/table/{$type->key}";

    $this->get($url)->assertNotFound();
    $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
    $this->actingAs($owner)->get($url)->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
});

it('returns 404 for an unknown dataset or device', function () {
    [$device] = deviceWithData(['is_public' => true]);

    $this->get("/devices/{$device->uuid}/table/nope")->assertNotFound();
    $this->get('/devices/00000000-0000-7000-8000-000000000000/table/temperature')->assertNotFound();
});

it('formats popup values with the display settings of the dataset', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    $type->update(['decimals' => 2]);
    reading($device, $type, 1.23456, now()->subHour());

    $this->get("/devices/{$device->uuid}/table/{$type->key}")->assertSee('1.23');
});

it('shows current-condition tiles and a data table button instead of an inline table', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    reading($device, $type, 21.5, now()->subMinutes(10));

    $html = $this->get("/devices/{$device->uuid}")->assertOk()->getContent();

    expect($html)->toContain('aria-label="Current conditions"')->toContain('21.5')
        ->toContain('hx-get="'.url("/devices/{$device->uuid}/table/{$type->key}").'"')
        ->toContain('data-dialog')->toContain('data-period-state')
        ->and($html)->not->toContain('data-rows')->not->toContain('<tbody');
});
