<?php

use App\Models\Device;
use App\Models\Measurement;
use App\Models\MeasurementType;
use App\Models\User;
use App\Support\DisplaySettings;

it('creates a private type with display settings for a normal user (is_public is ignored)', function () {
    $user = User::factory()->create();

    $r = $this->actingAs($user)->postJson('/api/measurement-types', [
        'key' => 'price', 'name' => 'Price', 'unit' => '€', 'unit_position' => 'before', 'unit_space' => false, 'decimals' => 2,
        'color' => '#112233', 'chart_type' => 'bar', 'y_min' => 0, 'y_max' => 100, 'is_public' => true,
    ])->assertCreated();

    $r->assertJsonPath('data.is_public', false)->assertJsonPath('data.unit_position', 'before')->assertJsonPath('data.chart_type', 'bar')->assertJsonPath('data.decimals', 2);
    $this->assertDatabaseHas('measurement_types', ['key' => 'price', 'created_by' => $user->id, 'is_public' => false]);
});

it('lets an administrator create a global type', function () {
    $this->actingAs(User::factory()->admin()->create())->postJson('/api/measurement-types', ['key' => 'uv', 'name' => 'UV', 'unit' => 'UVI', 'is_public' => true])
        ->assertCreated()->assertJsonPath('data.is_public', true);
});

it('validates display settings', function (array $bad, string $field) {
    $this->actingAs(User::factory()->create())->postJson('/api/measurement-types', $bad + ['key' => 'x', 'name' => 'X', 'unit' => 'x'])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['unit_position' => 'middle'], 'unit_position'],
    [['decimals' => 9], 'decimals'],
    [['color' => 'red'], 'color'],
    [['chart_type' => 'pie'], 'chart_type'],
    [['y_min' => 10, 'y_max' => 5], 'y_max'],
]);

it('lists only global types and the callers own private types via the API', function () {
    $alice = User::factory()->create();
    MeasurementType::factory()->create(['key' => 'g1', 'is_public' => true]);
    MeasurementType::factory()->create(['key' => 'a1', 'created_by' => $alice->id]);
    MeasurementType::factory()->create(['key' => 'b1', 'created_by' => User::factory()->create()->id]);

    $keys = collect($this->actingAs($alice)->getJson('/api/measurement-types')->json('data'))->pluck('key')->all();

    expect($keys)->toContain('g1', 'a1')->not->toContain('b1');
});

it('does not allow a type key to be changed on update', function () {
    $user = User::factory()->create();
    $type = MeasurementType::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)->putJson("/api/measurement-types/{$type->id}", ['key' => 'renamed'])->assertUnprocessable();
    $this->putJson("/api/measurement-types/{$type->id}", ['name' => 'New name', 'decimals' => 3])->assertOk()->assertJsonPath('data.decimals', 3);
});

it('refuses another users private type when creating a device', function () {
    $alice = User::factory()->create();
    $secret = MeasurementType::factory()->create(['created_by' => $alice->id]);

    $this->actingAs(User::factory()->create())->postJson('/api/devices', ['name' => 'D', 'types' => [$secret->id]])->assertUnprocessable()->assertJsonValidationErrors('types.0');
    $this->actingAs($alice)->postJson('/api/devices', ['name' => 'D', 'types' => [$secret->id]])->assertCreated();
});

it('validates types against the device owner when an admin edits a device', function () {
    $alice = User::factory()->create();
    $mine = MeasurementType::factory()->create(['created_by' => $alice->id]);
    $device = Device::factory()->for($alice, 'owner')->create();
    $adminsOwn = MeasurementType::factory()->create(['created_by' => User::factory()->admin()->create()->id]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->putJson("/api/devices/{$device->uuid}", ['types' => [$mine->id]])->assertOk();
    $this->putJson("/api/devices/{$device->uuid}", ['types' => [$adminsOwn->id]])->assertUnprocessable();
});

it('applies per-device overrides on top of the type defaults', function () {
    $type = MeasurementType::factory()->create(['key' => 'temperature', 'unit' => '°C', 'decimals' => 1, 'color' => '#111111', 'is_public' => true]);
    $user = User::factory()->create();

    $id = $this->actingAs($user)->postJson('/api/devices', [
        'name' => 'D', 'is_public' => true, 'types' => [$type->id],
        'type_settings' => [$type->id => ['decimals' => 3, 'unit' => 'K', 'unit_position' => 'before', 'color' => null]],
    ])->assertCreated()->json('data.id');
    Measurement::create(['device_id' => Device::where('uuid', $id)->value('id'), 'measurement_type_id' => $type->id, 'value' => 1.23456, 'measured_at' => now()->subHour(), 'source' => 'ingest']);

    $res = $this->getJson("/api/public/devices/{$id}/measurements?type=temperature")->assertOk();

    $res->assertJsonPath('display.decimals', 3)->assertJsonPath('display.unit', 'K')->assertJsonPath('display.unit_position', 'before')
        ->assertJsonPath('display.color', '#111111')->assertJsonPath('data.0.unit', 'K');
    // another device keeps the type defaults
    [$other] = deviceWithData(['is_public' => true]);
    $other->types()->sync([$type->id]);
    $this->getJson("/api/public/devices/{$other->uuid}/measurements?type=temperature")->assertJsonPath('display.decimals', 1)->assertJsonPath('display.unit', '°C');
});

it('rejects invalid per-device overrides', function () {
    $type = MeasurementType::factory()->create(['is_public' => true]);

    $this->actingAs(User::factory()->create())->postJson('/api/devices', ['name' => 'D', 'types' => [$type->id], 'type_settings' => [$type->id => ['decimals' => 12, 'color' => 'blue']]])
        ->assertUnprocessable()->assertJsonValidationErrors(["type_settings.{$type->id}.decimals", "type_settings.{$type->id}.color"]);
});

it('formats values by unit position, spacing and decimals', function (array $d, string $expected) {
    expect(DisplaySettings::format(21.456, $d + ['unit' => '€', 'unit_position' => 'after', 'unit_space' => true, 'decimals' => 1]))->toBe($expected);
})->with([
    [[], "21.5\u{00A0}€"],
    [['unit_position' => 'before'], "€\u{00A0}21.5"],
    [['unit_position' => 'before', 'unit_space' => false, 'decimals' => 2], '€21.46'],
    [['unit_position' => 'none', 'decimals' => 0], '21'],
]);

it('shows missing values as text, never as a number', function () {
    expect(DisplaySettings::format(null, ['unit' => '°C', 'unit_position' => 'after', 'unit_space' => true, 'decimals' => 1]))->toBe('Missing value');
});

it('renders formatted values on device cards using the display settings', function () {
    $type = MeasurementType::factory()->create(['unit' => '€', 'unit_position' => 'before', 'decimals' => 2, 'is_public' => true]);
    [$device] = deviceWithData(['is_public' => true]);
    $device->types()->sync([$type->id]);
    Measurement::create(['device_id' => $device->id, 'measurement_type_id' => $type->id, 'value' => 3.14159, 'measured_at' => now()->subMinutes(5), 'source' => 'ingest']);

    $this->get('/en/devices')->assertSee("€\u{00A0}3.14", false);
});

it('stores a category on a type and rejects unknown categories', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson('/api/measurement-types', ['key' => 'o3', 'name' => 'Ozone', 'unit' => 'ppb', 'category' => 'air'])->assertCreated()->assertJsonPath('data.category', 'air');
    $this->postJson('/api/measurement-types', ['key' => 'o4', 'name' => 'X', 'unit' => 'x', 'category' => 'nonsense'])->assertUnprocessable()->assertJsonValidationErrors('category');
    $this->postJson('/api/measurement-types', ['key' => 'o5', 'name' => 'Y', 'unit' => 'y'])->assertCreated()->assertJsonPath('data.category', 'other');
});

it('lets the type form pick a category and lists it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/manage/types/create')->assertSee('Air quality')->assertSee('Water and soil');
    $this->post('/manage/types', ['key' => 'soil', 'name' => 'Soil moisture', 'unit' => '%', 'category' => 'water']);
    $this->get('/manage/types?scope=mine')->assertSee('Water and soil');
});

it('exposes the category in the measurement response', function () {
    $type = MeasurementType::factory()->create(['key' => 'temperature', 'category' => 'weather', 'is_public' => true]);
    [$device] = deviceWithData(['is_public' => true]);
    $device->types()->sync([$type->id]);

    $this->getJson("/api/public/devices/{$device->uuid}/measurements?type=temperature")->assertJsonPath('type.category', 'weather');
});
