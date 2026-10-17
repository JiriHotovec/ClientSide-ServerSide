<?php

use App\Models\Device;
use App\Models\MeasurementType;
use App\Models\User;

beforeEach(function () {
    $this->type = MeasurementType::factory()->create(['key' => 'temperature', 'unit' => '°C', 'color' => '#aa0000', 'is_public' => true]);
});

it('creates a device from the html form with per-type overrides', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/manage/devices', [
        '_form' => 1, 'name' => 'Roof', 'is_public' => '1', 'types' => [$this->type->id],
        'ts' => [$this->type->id => ['unit' => '', 'decimals' => '3', 'color' => '#aa0000', 'unit_position' => 'before']],
    ])->assertRedirect(route('manage.devices'))->assertSessionHas('status');

    $device = Device::where('name', 'Roof')->firstOrFail();
    $pivot = $device->types->first()->pivot;
    expect($device->user_id)->toBe($user->id)->and($device->is_public)->toBeTrue()
        ->and($pivot->decimals)->toBe(3)->and($pivot->unit_position)->toBe('before')
        ->and($pivot->color)->toBeNull()->and($pivot->unit)->toBeNull(); // unchanged color / blank unit = inherit
});

it('treats an unchecked visibility switch and no datasets as private and empty on update', function () {
    $user = User::factory()->create();
    $device = Device::factory()->for($user, 'owner')->public()->create();
    $device->types()->attach($this->type);

    $this->actingAs($user)->put("/manage/devices/{$device->uuid}", ['_form' => 1, 'name' => 'Renamed', 'is_public' => '0'])->assertRedirect(route('manage.devices'));

    $device->refresh();
    expect($device->name)->toBe('Renamed')->and($device->is_public)->toBeFalse()->and($device->types)->toHaveCount(0);
});

it('sends invalid form input back with errors and the old values', function () {
    $this->actingAs(User::factory()->create())->from('/manage/devices/create')
        ->post('/manage/devices', ['_form' => 1, 'name' => '', 'location' => 'Brno', 'source_url' => 'http://127.0.0.1/x'])
        ->assertRedirect('/manage/devices/create');

    $this->get('/manage/devices/create')->assertSee('Please fix the following')->assertSee('The name field is required.')->assertSee('private or reserved address')->assertSee('value="Brno"', false);
});

it('forbids form actions on foreign devices', function () {
    $device = Device::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->put("/manage/devices/{$device->uuid}", ['_form' => 1, 'name' => 'x'])->assertForbidden();
    $this->delete("/manage/devices/{$device->uuid}")->assertForbidden();
    $this->patch("/manage/devices/{$device->uuid}/visibility", ['is_public' => 1])->assertForbidden();
    $this->post("/manage/devices/{$device->uuid}/token")->assertForbidden();
    $this->assertDatabaseHas('devices', ['id' => $device->id]);
});

it('deletes a device, toggles visibility and shows a new token once', function () {
    $user = User::factory()->create();
    $device = Device::factory()->for($user, 'owner')->private()->create();
    $this->actingAs($user);

    $this->from('/manage/devices')->patch("/manage/devices/{$device->uuid}/visibility", ['is_public' => 1])->assertRedirect('/manage/devices');
    expect($device->fresh()->is_public)->toBeTrue();

    $this->from('/manage/devices')->post("/manage/devices/{$device->uuid}/token")->assertSessionHas('status', fn ($m) => str_contains($m, 'mv_'));
    $this->get('/manage/devices')->assertSee('New token for');
    $this->get('/manage/devices')->assertDontSee('New token for'); // flashed only once

    $this->delete("/manage/devices/{$device->uuid}")->assertRedirect(route('manage.devices'));
    $this->assertDatabaseMissing('devices', ['id' => $device->id]);
});

it('lets admins use the form actions on any device', function () {
    $device = Device::factory()->create();

    $this->actingAs(User::factory()->admin()->create())->delete("/manage/devices/{$device->uuid}")->assertRedirect(route('manage.devices'));
    $this->assertDatabaseMissing('devices', ['id' => $device->id]);
});

it('creates, edits and deletes a measurement type from the html form', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post('/manage/types', ['key' => 'uv_index', 'name' => 'UV index', 'unit' => 'UVI', 'unit_position' => 'after', 'unit_space' => '1', 'decimals' => '1', 'chart_type' => 'line', 'color' => '#123456', 'is_public' => '1'])
        ->assertRedirect(route('manage.types'));
    $type = MeasurementType::where('key', 'uv_index')->firstOrFail();
    expect($type->created_by)->toBe($user->id)->and($type->is_public)->toBeFalse(); // users cannot publish globally

    $this->put("/manage/types/{$type->id}", ['name' => 'UV', 'unit' => 'UVI', 'decimals' => '2'])->assertRedirect(route('manage.types'));
    expect($type->fresh()->decimals)->toBe(2);

    $this->delete("/manage/types/{$type->id}")->assertRedirect(route('manage.types'));
    $this->assertDatabaseMissing('measurement_types', ['id' => $type->id]);
});

it('keeps an in-use type and says so', function () {
    $user = User::factory()->create();
    $type = MeasurementType::factory()->create(['created_by' => $user->id]);
    Device::factory()->for($user, 'owner')->create()->types()->attach($type);

    $this->actingAs($user)->from('/manage/types')->delete("/manage/types/{$type->id}")->assertSessionHas('status', fn ($m) => str_contains($m, 'in use'));
    $this->assertDatabaseHas('measurement_types', ['id' => $type->id]);
});

it('lets an administrator publish a type globally through the form', function () {
    $this->actingAs(User::factory()->admin()->create())->post('/manage/types', ['key' => 'gust', 'name' => 'Gust', 'unit' => 'm/s', 'is_public' => '1'])->assertRedirect();

    expect(MeasurementType::where('key', 'gust')->value('is_public'))->toBeTrue();
});

it('forbids editing foreign types', function () {
    $type = MeasurementType::factory()->create(['created_by' => User::factory()->create()->id]);

    $this->actingAs(User::factory()->create())->put("/manage/types/{$type->id}", ['name' => 'x'])->assertForbidden();
    $this->delete("/manage/types/{$type->id}")->assertForbidden();
});

it('tells htmx to navigate after a successful login or registration', function () {
    $user = User::factory()->create();

    $this->post('/en/login', ['email' => $user->email, 'password' => 'password'], ['HX-Request' => 'true'])->assertNoContent()->assertHeader('HX-Redirect', route('manage.overview'));
});

it('redirects htmx registration to the workspace and shows errors inline otherwise', function () {
    $this->post('/en/register', ['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'Secret12345', 'password_confirmation' => 'Secret12345'], ['HX-Request' => 'true'])
        ->assertNoContent()->assertHeader('HX-Redirect', route('manage.overview'));
});

it('boosts the auth and workspace forms with htmx', function () {
    $this->get('/en/login')->assertSee('hx-boost="true"', false)->assertSee('hx-select="#main"', false);
    $this->actingAs(User::factory()->create())->get('/manage/devices/create')->assertSee('hx-boost="true"', false)->assertSee('name="_form"', false);
});

it('does not boost device card links, which lead to a different page than the list', function () {
    Device::factory()->public()->create();

    $this->get('/en/devices')->assertSee('class="device-card"', false)->assertSee('hx-boost="false"', false);
});
