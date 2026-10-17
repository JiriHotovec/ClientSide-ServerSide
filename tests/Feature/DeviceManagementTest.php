<?php

use App\Models\Device;
use App\Models\MeasurementType;
use App\Models\User;

beforeEach(function () {
    $this->type = MeasurementType::create(['key' => 'temperature', 'name' => 'Temperature', 'unit' => '°C']);
    $this->type->forceFill(['is_system' => true, 'is_public' => true])->save();
});

it('lets a user create a device that is owned by them (A9)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/devices', ['name' => 'Roof', 'is_public' => true, 'types' => [$this->type->id]])
        ->assertCreated()->assertJsonPath('data.name', 'Roof')->assertJsonPath('data.is_public', true);

    $this->assertDatabaseHas('devices', ['name' => 'Roof', 'user_id' => $user->id]);
});

it('ignores attempts to assign another owner via mass assignment', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)->postJson('/api/devices', ['name' => 'Roof', 'user_id' => $other->id])->assertCreated();

    $this->assertDatabaseHas('devices', ['name' => 'Roof', 'user_id' => $user->id]);
});

it('validates device input', function () {
    $this->actingAs(User::factory()->create())->postJson('/api/devices', ['name' => '', 'types' => [999]])
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'types.0']);
});

it('lists only own devices for users and all for admins (A8)', function () {
    $a = User::factory()->create();
    Device::factory()->for($a, 'owner')->create();
    Device::factory()->create();

    $this->actingAs($a)->getJson('/api/devices')->assertJsonCount(1, 'data');
    $this->actingAs(User::factory()->admin()->create())->getJson('/api/devices')->assertJsonCount(2, 'data');
});

it('updates and deletes own devices (A9)', function () {
    $user = User::factory()->create();
    $device = Device::factory()->for($user, 'owner')->create(['name' => 'Old']);

    $this->actingAs($user)->putJson("/api/devices/{$device->id}", ['name' => 'New'])->assertOk()->assertJsonPath('data.name', 'New');
    $this->assertDatabaseHas('devices', ['id' => $device->id, 'name' => 'New']);

    $this->actingAs($user)->deleteJson("/api/devices/{$device->id}")->assertNoContent();
    $this->assertDatabaseMissing('devices', ['id' => $device->id]);
});

it('rejects requests with another user\'s device id on every protected endpoint (A8)', function () {
    $owner = User::factory()->create();
    $device = Device::factory()->for($owner, 'owner')->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder);
    $this->getJson("/api/devices/{$device->id}")->assertForbidden();
    $this->putJson("/api/devices/{$device->id}", ['name' => 'Hacked'])->assertForbidden();
    $this->deleteJson("/api/devices/{$device->id}")->assertForbidden();
    $this->patchJson("/api/devices/{$device->id}/visibility", ['is_public' => true])->assertForbidden();
    $this->postJson("/api/devices/{$device->id}/token")->assertForbidden();
    $this->getJson("/api/devices/{$device->id}/measurements")->assertForbidden();
    $this->get("/manage/devices/{$device->id}/edit")->assertForbidden();

    $this->assertDatabaseHas('devices', ['id' => $device->id, 'name' => $device->name]);
});

it('rejects unauthenticated access to protected endpoints with 401', function () {
    $device = Device::factory()->create();

    $this->getJson('/api/devices')->assertUnauthorized();
    $this->postJson('/api/devices', ['name' => 'x'])->assertUnauthorized();
    $this->deleteJson("/api/devices/{$device->id}")->assertUnauthorized();
    $this->getJson("/api/devices/{$device->id}/measurements")->assertUnauthorized();
});

it('lets admins manage every device (A8)', function () {
    $device = Device::factory()->create(['name' => 'Foreign']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->putJson("/api/devices/{$device->id}", ['name' => 'Renamed'])->assertOk();
    $this->actingAs($admin)->deleteJson("/api/devices/{$device->id}")->assertNoContent();
});

it('changes visibility and reflects it immediately on public pages and APIs (A10)', function () {
    $user = User::factory()->create();
    $device = Device::factory()->for($user, 'owner')->private()->create();

    $this->getJson("/api/public/devices/{$device->id}")->assertNotFound();

    $this->actingAs($user)->patchJson("/api/devices/{$device->id}/visibility", ['is_public' => true])->assertOk()->assertJsonPath('data.is_public', true);
    auth()->logout();
    $this->getJson("/api/public/devices/{$device->id}")->assertOk();
    $this->get('/devices')->assertSee($device->name);

    $this->actingAs($user)->patchJson("/api/devices/{$device->id}/visibility", ['is_public' => false])->assertOk();
    auth()->logout();
    $this->getJson("/api/public/devices/{$device->id}")->assertNotFound();
    $this->get('/devices')->assertDontSee($device->name);
});

it('lets an admin change visibility of any device (A10)', function () {
    $device = Device::factory()->private()->create();

    $this->actingAs(User::factory()->admin()->create())->patchJson("/api/devices/{$device->id}/visibility", ['is_public' => true])->assertOk();
    expect($device->fresh()->is_public)->toBeTrue();
});

it('requires a boolean for visibility', function () {
    $user = User::factory()->create();
    $device = Device::factory()->for($user, 'owner')->create();

    $this->actingAs($user)->patchJson("/api/devices/{$device->id}/visibility", ['is_public' => 'maybe'])->assertUnprocessable();
});

it('shows a user only own devices on the personal dashboard and switches devices via htmx (A1)', function () {
    $user = User::factory()->create();
    $mine = Device::factory()->for($user, 'owner')->create(['name' => 'Mine']);
    $theirs = Device::factory()->create(['name' => 'Theirs']);

    $this->actingAs($user)->get('/dashboard')->assertSee('Mine')->assertDontSee('Theirs');
    $this->get("/dashboard/devices/{$mine->id}", ['HX-Request' => 'true'])->assertOk()->assertSee('Mine')->assertDontSee('<html', false);
    $this->get("/dashboard/devices/{$theirs->id}", ['HX-Request' => 'true'])->assertForbidden();
});

it('lets admins open the edit page of any device', function () {
    $device = Device::factory()->create(['name' => 'Theirs']);

    $this->actingAs(User::factory()->admin()->create())->get("/manage/devices/{$device->id}/edit")->assertOk()->assertSee('Theirs');
});

it('regenerates an ingest token and stores only its hash', function () {
    $user = User::factory()->create();
    $device = Device::factory()->for($user, 'owner')->create();

    $token = $this->actingAs($user)->postJson("/api/devices/{$device->id}/token")->assertOk()->json('token');

    expect($token)->toStartWith('mv_')
        ->and($device->fresh()->ingest_token_hash)->toBe(Device::hashToken($token))->not->toBe($token);
});

it('manages measurement types: create, pick, protect in-use and foreign ones', function () {
    $user = User::factory()->create();

    $id = $this->actingAs($user)->postJson('/api/measurement-types', ['key' => 'uv_index', 'name' => 'UV', 'unit' => 'UVI'])->assertCreated()->json('data.id');
    $this->postJson('/api/measurement-types', ['key' => 'uv_index', 'name' => 'UV', 'unit' => 'UVI'])->assertUnprocessable();
    $this->postJson('/api/measurement-types', ['key' => 'Bad Key', 'name' => 'x', 'unit' => 'x'])->assertUnprocessable();

    $this->actingAs(User::factory()->create())->deleteJson("/api/measurement-types/{$id}")->assertForbidden();

    $this->actingAs($user)->postJson('/api/devices', ['name' => 'D', 'types' => [$id]])->assertCreated();
    $this->deleteJson("/api/measurement-types/{$id}")->assertStatus(409);

    $this->deleteJson("/api/measurement-types/{$this->type->id}")->assertForbidden(); // system type
});

it('deletes an unused own measurement type', function () {
    $user = User::factory()->create();
    $id = $this->actingAs($user)->postJson('/api/measurement-types', ['key' => 'x1', 'name' => 'X', 'unit' => 'x'])->json('data.id');

    $this->deleteJson("/api/measurement-types/{$id}")->assertNoContent();
});
