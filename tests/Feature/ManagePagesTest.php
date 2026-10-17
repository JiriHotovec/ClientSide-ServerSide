<?php

use App\Models\Device;
use App\Models\MeasurementType;
use App\Models\User;

it('protects every admin page from guests', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with(['/manage', '/manage/devices', '/manage/devices/create', '/manage/types', '/manage/types/create']);

it('renders every admin page for a user and an administrator', function (string $url) {
    $this->actingAs(User::factory()->create())->get($url)->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
})->with(['/manage', '/manage/devices', '/manage/devices/create', '/manage/types', '/manage/types/create']);

it('shows a user only their own devices on the panel and the device list', function () {
    $user = User::factory()->create();
    Device::factory()->for($user, 'owner')->create(['name' => 'Mine']);
    Device::factory()->create(['name' => 'Theirs']);

    foreach (['/manage', '/manage/devices'] as $url) {
        $this->actingAs($user)->get($url)->assertSee('Mine')->assertDontSee('Theirs');
    }
});

it('does not let a user widen the scope to all devices', function () {
    $user = User::factory()->create();
    Device::factory()->create(['name' => 'Theirs']);

    $this->actingAs($user)->get('/manage?scope=all')->assertDontSee('Theirs');
});

it('shows an administrator their own devices first and everything with scope=all', function () {
    $admin = User::factory()->admin()->create();
    Device::factory()->for($admin, 'owner')->create(['name' => 'AdminOwn']);
    Device::factory()->create(['name' => 'Foreign']);

    $this->actingAs($admin)->get('/manage')->assertSee('AdminOwn')->assertDontSee('Foreign');
    $this->get('/manage?scope=all')->assertSee('AdminOwn')->assertSee('Foreign');
    $this->get('/manage/devices?scope=all')->assertSee('Foreign');
});

it('filters devices by search, visibility and data source', function () {
    $user = User::factory()->create();
    Device::factory()->for($user, 'owner')->public()->create(['name' => 'Alpha', 'location' => 'Oslo']);
    Device::factory()->for($user, 'owner')->private()->create(['name' => 'Beta', 'source_url' => 'https://x.example/y', 'last_error' => 'HTTP 500']);
    $this->actingAs($user);

    $this->get('/manage/devices?q=oslo')->assertSee('Alpha')->assertDontSee('Beta');
    $this->get('/manage/devices?visibility=private')->assertSee('Beta')->assertDontSee('Alpha');
    $this->get('/manage/devices?source=pull')->assertSee('Beta')->assertDontSee('Alpha');
    $this->get('/manage/devices?source=error')->assertSee('Beta')->assertDontSee('Alpha');
    $this->get('/manage/devices?source=push')->assertSee('Alpha')->assertDontSee('Beta');
});

it('paginates the panel and keeps filters in the page links', function () {
    $user = User::factory()->create();
    Device::factory()->count(12)->for($user, 'owner')->create(['location' => 'Brno']);

    $res = $this->actingAs($user)->get('/manage?q=Brno')->assertSee('Showing 1–9 of 12');
    $res->assertSee('q=Brno', false)->assertSee('aria-label="Page navigation"', false);
    $this->get('/manage?q=Brno&page=2')->assertSee('Showing 10–12 of 12');
});

it('paginates and searches the public device list', function () {
    Device::factory()->count(11)->public()->create(['location' => 'Ostrava']);
    Device::factory()->public()->create(['name' => 'Needle', 'location' => 'Kladno']);

    $this->get('/en/devices')->assertSee('Showing 1–9 of 12');
    $this->get('/en/devices?q=Needle')->assertSee('Needle')->assertDontSee('Ostrava');
});

it('serves the filter results as a full page that htmx can select from', function () {
    $user = User::factory()->create();
    Device::factory()->for($user, 'owner')->create(['name' => 'Mine']);

    $this->actingAs($user)->get('/manage/devices?q=Mine', ['HX-Request' => 'true'])->assertOk()->assertSee('id="results"', false)->assertSee('Mine');
});

it('only offers editing of own devices on the edit page', function () {
    $device = Device::factory()->create();

    $this->actingAs(User::factory()->create())->get("/manage/devices/{$device->uuid}/edit")->assertForbidden();
});

it('hides private types of other users in the type list and the device form', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    MeasurementType::factory()->create(['name' => 'Global one', 'is_public' => true]);
    MeasurementType::factory()->create(['name' => 'Alice private', 'created_by' => $alice->id]);

    $this->actingAs($bob);
    $this->get('/manage/types')->assertSee('Global one')->assertDontSee('Alice private');
    $this->get('/manage/devices/create')->assertSee('Global one')->assertDontSee('Alice private');
    $this->actingAs($alice)->get('/manage/types')->assertSee('Alice private');
    $this->get('/manage/types?scope=mine')->assertSee('Alice private')->assertDontSee('Global one');
    $this->actingAs(User::factory()->admin()->create())->get('/manage/types?scope=all')->assertSee('Alice private')->assertSee('Global one');
});

it('only lets the creator or an admin open the type edit page', function () {
    $alice = User::factory()->create();
    $type = MeasurementType::factory()->create(['created_by' => $alice->id]);

    $this->actingAs($alice)->get("/manage/types/{$type->id}/edit")->assertOk();
    $this->actingAs(User::factory()->create())->get("/manage/types/{$type->id}/edit")->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get("/manage/types/{$type->id}/edit")->assertOk();
});

it('does not offer creating a device on the devices and data sources page (it lives in the overview)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/manage/devices')->assertDontSee(route('manage.devices.create'), false)->assertDontSee('Add a device');
    $this->get('/manage/devices?q=nothing')->assertSee('No devices match.')->assertDontSee(route('manage.devices.create'), false);
    $this->get('/manage')->assertSee(route('manage.devices.create'), false)->assertSee('Add a device');
});

it('shows the visibility of a device in the list without a quick toggle button (it is changed in the device form)', function () {
    $user = User::factory()->create();
    Device::factory()->for($user, 'owner')->public()->create(['name' => 'Alpha']);
    Device::factory()->for($user, 'owner')->private()->create(['name' => 'Beta']);

    $this->actingAs($user)->get('/manage/devices')->assertSee('Public')->assertSee('Private')
        ->assertDontSee('Make private')->assertDontSee('Make public')->assertDontSee('/visibility', false);
});

it('does not put a token button into the actions of the device list (tokens live on the Data source tab)', function () {
    $user = User::factory()->create();
    $device = Device::factory()->for($user, 'owner')->create(['name' => 'Roof']);

    $this->actingAs($user)->get('/manage/devices')->assertDontSee('New token')->assertDontSee('/token', false)
        ->assertSee('Edit')->assertSee('Delete');
    $this->get("/manage/devices/{$device->uuid}/edit")->assertSee('Generate a new token');
});
