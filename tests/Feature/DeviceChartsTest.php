<?php

use App\Models\Device;
use App\Models\DeviceChart;
use App\Models\MeasurementType;
use App\Models\User;
use App\Support\ChartKinds;

beforeEach(function () {
    $this->temp = MeasurementType::factory()->create(['key' => 'temperature', 'name' => 'Temperature', 'is_public' => true]);
    $this->wind = MeasurementType::factory()->create(['key' => 'wind_direction', 'name' => 'Wind direction', 'unit' => '°', 'is_public' => true]);
});

function chartsOf(Device $device): array
{
    return $device->charts()->get()->map(fn ($c) => [$c->measurement_type_id, $c->kind, $c->title])->all();
}

it('offers every chart style for datasets and type defaults', function () {
    expect(ChartKinds::keys())->toBe(['line', 'area', 'step', 'bar', 'scatter', 'gauge', 'rose']);

    $this->actingAs(User::factory()->create())->postJson('/api/measurement-types', ['key' => 'g1', 'name' => 'G', 'unit' => 'x', 'chart_type' => 'gauge'])->assertCreated()->assertJsonPath('data.chart_type', 'gauge');
    $this->postJson('/api/measurement-types', ['key' => 'g2', 'name' => 'G', 'unit' => 'x', 'chart_type' => 'pie'])->assertUnprocessable();
});

it('lets the owner arrange several charts per device through the form, in the submitted order', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/manage/devices', [
        '_form' => 1, 'name' => 'Roof', 'types' => [$this->temp->id, $this->wind->id],
        'charts' => [
            '812' => ['type' => $this->wind->id, 'kind' => 'rose', 'title' => ''],
            '3' => ['type' => $this->temp->id, 'kind' => 'gauge', 'title' => 'Now'],
            '77' => ['type' => $this->temp->id, 'kind' => 'area', 'title' => ' History '],
        ],
    ])->assertRedirect(route('manage.devices'));

    expect(chartsOf(Device::firstWhere('name', 'Roof')))->toBe([
        [$this->wind->id, 'rose', null], [$this->temp->id, 'gauge', 'Now'], [$this->temp->id, 'area', 'History'],
    ]);
});

it('replaces the arrangement on update and clears it when no rows are submitted', function () {
    $user = User::factory()->create();
    $device = Device::factory()->for($user, 'owner')->create();
    $device->types()->attach([$this->temp->id, $this->wind->id]);
    DeviceChart::factory()->for($device)->create(['measurement_type_id' => $this->temp->id, 'kind' => 'bar']);

    $this->actingAs($user)->put("/manage/devices/{$device->uuid}", ['_form' => 1, 'name' => 'D', 'types' => [$this->temp->id, $this->wind->id],
        'charts' => [['type' => $this->wind->id, 'kind' => 'step']]]);
    expect(chartsOf($device))->toBe([[$this->wind->id, 'step', null]]);

    $this->put("/manage/devices/{$device->uuid}", ['_form' => 1, 'name' => 'D', 'types' => [$this->temp->id]]);
    expect(chartsOf($device))->toBe([]);
});

it('rejects charts of datasets the device does not report, unknown styles and too many charts', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson('/api/devices', ['name' => 'D', 'types' => [$this->temp->id], 'charts' => [['type' => $this->wind->id, 'kind' => 'line']]])
        ->assertUnprocessable()->assertJsonValidationErrors('charts.0.type');
    $this->postJson('/api/devices', ['name' => 'D', 'types' => [$this->temp->id], 'charts' => [['type' => $this->temp->id, 'kind' => 'pie']]])
        ->assertUnprocessable()->assertJsonValidationErrors('charts.0.kind');
    $many = array_fill(0, ChartKinds::MAX_PER_DEVICE + 1, ['type' => $this->temp->id, 'kind' => 'line']);
    $this->postJson('/api/devices', ['name' => 'D', 'types' => [$this->temp->id], 'charts' => $many])->assertUnprocessable()->assertJsonValidationErrors('charts');
});

it('creates charts through the REST API and returns them', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson('/api/devices', ['name' => 'D', 'types' => [$this->temp->id], 'charts' => [['type' => $this->temp->id, 'kind' => 'gauge', 'title' => 'Now']]])
        ->assertCreated()->assertJsonPath('data.charts.0', ['type' => 'temperature', 'kind' => 'gauge', 'title' => 'Now']);
});

it('removes the charts of a dataset that is taken off the device', function () {
    $user = User::factory()->create();
    $device = Device::factory()->for($user, 'owner')->create();
    $device->types()->attach([$this->temp->id, $this->wind->id]);
    DeviceChart::factory()->for($device)->create(['measurement_type_id' => $this->wind->id, 'kind' => 'rose']);

    $this->actingAs($user)->putJson("/api/devices/{$device->uuid}", ['types' => [$this->temp->id]])->assertOk();

    expect(chartsOf($device))->toBe([]);
});

it('shows the arranged charts on the dashboard with their style and title', function () {
    [$device] = deviceWithData(['is_public' => true]);
    $device->types()->attach($this->wind);
    DeviceChart::factory()->for($device)->create(['measurement_type_id' => $this->wind->id, 'kind' => 'rose', 'title' => 'Where the wind blows', 'position' => 0]);
    DeviceChart::factory()->for($device)->create(['measurement_type_id' => $this->wind->id, 'kind' => 'gauge', 'position' => 1]);

    $html = $this->get("/en/devices/{$device->uuid}")->getContent();

    expect(substr_count($html, 'data-card'))->toBe(2)
        ->and($html)->toContain('data-kind="rose"')->toContain('data-kind="gauge"')->toContain('Where the wind blows')
        ->and(strpos($html, 'data-kind="rose"'))->toBeLessThan(strpos($html, 'data-kind="gauge"'));
});

it('falls back to one default chart per dataset without an arrangement', function () {
    [$device] = deviceWithData(['is_public' => true]);
    $device->types()->attach($this->wind);

    $html = $this->get("/en/devices/{$device->uuid}")->getContent();

    expect(substr_count($html, 'data-card'))->toBe(2)->and($html)->not->toContain('data-kind=');
});

it('serves the chart editor row fragment limited to the ticked datasets', function () {
    $this->actingAs(User::factory()->create());

    $res = $this->get('/manage/devices/chart-row?types[]='.$this->temp->id, ['HX-Request' => 'true'])->assertOk();
    $res->assertSee('data-chart-row', false)->assertSee('Temperature')->assertDontSee('Wind direction')->assertDontSee('<html', false);
    foreach (ChartKinds::keys() as $kind) {
        $res->assertSee("value=\"{$kind}\"", false);
    }

    $this->get('/manage/devices/chart-row')->assertSee('Select at least one dataset above first.');
});

it('renders the chart editor with its rows on the device form', function () {
    $user = User::factory()->create();
    $device = Device::factory()->for($user, 'owner')->create();
    $device->types()->attach($this->temp);
    DeviceChart::factory()->for($device)->create(['measurement_type_id' => $this->temp->id, 'kind' => 'step', 'title' => 'Steps']);

    $this->actingAs($user)->get("/manage/devices/{$device->uuid}/edit")->assertSee('charts-editor', false)->assertSee('value="Steps"', false)->assertSee('hx-get="'.route('manage.charts.row').'"', false);
    $this->get('/manage/devices/create')->assertSee('Add a chart');
});

it('keeps chart rows when validation fails', function () {
    $this->actingAs(User::factory()->create())->from('/manage/devices/create')->post('/manage/devices', [
        '_form' => 1, 'name' => '', 'types' => [$this->temp->id], 'charts' => [['type' => $this->temp->id, 'kind' => 'scatter', 'title' => 'Keep me']],
    ]);

    $this->get('/manage/devices/create')->assertSee('value="Keep me"', false);
});

it('translates chart styles', function () {
    $this->actingAs(User::factory()->create())->get('/manage/types/create', ['Accept-Language' => 'cs'])->assertSee('Větrná růžice')->assertSee('Ukazatel');
});
