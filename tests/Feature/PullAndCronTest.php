<?php

use App\Models\Device;
use App\Models\Measurement;
use App\Support\UrlGuard;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Deterministic DNS: every fake host resolves to a public IP, "internal.test" to a private one.
    app()->bind(UrlGuard::class, fn () => new class extends UrlGuard {
        protected function resolve(string $host): array
        {
            return $host === 'internal.test' ? ['10.0.0.5'] : ($host === 'nxdomain.test' ? [] : ['93.184.216.34']);
        }
    });
    config(['services.cron.secret' => 'topsecret']);
});

function pullDevice(array $state = []): Device
{
    [$device] = deviceWithData($state + ['source_url' => 'https://api.example.test/current', 'last_fetched_at' => null,
        'source_mapping' => ['timestamp' => 'current.time', 'values' => ['temperature' => 'current.temp']]]);

    return $device;
}

it('pulls and stores data from an external endpoint', function () {
    Http::fake(['api.example.test/*' => Http::response(['current' => ['time' => '2026-10-17T10:00:00Z', 'temp' => 18.2]])]);
    $device = pullDevice();

    $this->artisan('meteoview:fetch')->assertSuccessful();

    $this->assertDatabaseHas('measurements', ['device_id' => $device->id, 'value' => 18.2, 'source' => 'pull']);
    expect($device->fresh()->last_error)->toBeNull()->and($device->fresh()->last_fetched_at)->not->toBeNull();
});

it('stores missing source values as null, not zero', function () {
    Http::fake(['*' => Http::response(['current' => ['time' => '2026-10-17T10:00:00Z']])]);
    pullDevice();

    $this->artisan('meteoview:fetch');

    expect(Measurement::first()->value)->toBeNull();
});

it('supports list payloads via items_path', function () {
    Http::fake(['*' => Http::response(['hourly' => [['t' => '2026-10-17T09:00:00Z', 'v' => 1], ['t' => '2026-10-17T10:00:00Z', 'v' => 2]]])]);
    pullDevice(['source_mapping' => ['items_path' => 'hourly', 'timestamp' => 't', 'values' => ['temperature' => 'v']]]);

    $this->artisan('meteoview:fetch');

    expect(Measurement::count())->toBe(2);
});

it('records an understandable error when the source is down', function () {
    Http::fake(['*' => Http::response('boom', 503)]);
    $device = pullDevice();

    $this->artisan('meteoview:fetch');

    expect($device->fresh()->last_error)->toContain('HTTP 503');
    expect(Measurement::count())->toBe(0);
});

it('records an error when the source returns invalid JSON', function () {
    Http::fake(['*' => Http::response('not json', 200)]);
    $device = pullDevice();

    $this->artisan('meteoview:fetch');

    expect($device->fresh()->last_error)->toContain('valid JSON');
});

it('records a generic error on connection failure without leaking internals', function () {
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: timeout'));
    $device = pullDevice();

    $this->artisan('meteoview:fetch');

    expect($device->fresh()->last_error)->toBe('Data source is unavailable.');
});

it('does not let one failing device stop the others', function () {
    Http::fake(['bad.example.test/*' => Http::response('x', 500), 'api.example.test/*' => Http::response(['current' => ['time' => '2026-10-17T10:00:00Z', 'temp' => 3]])]);
    $bad = pullDevice(['source_url' => 'https://bad.example.test/x']);
    $good = pullDevice();

    $this->artisan('meteoview:fetch');

    expect($bad->fresh()->last_error)->not->toBeNull()->and($good->fresh()->last_error)->toBeNull();
    $this->assertDatabaseHas('measurements', ['device_id' => $good->id, 'value' => 3.0]);
});

it('respects the fetch interval unless forced', function () {
    Http::fake(['*' => Http::response(['current' => ['time' => '2026-10-17T10:00:00Z', 'temp' => 1]])]);
    pullDevice(['last_fetched_at' => now()->subMinutes(2), 'fetch_interval_min' => 15]);

    $this->artisan('meteoview:fetch');
    Http::assertNothingSent();

    $this->artisan('meteoview:fetch --force');
    Http::assertSentCount(1);
});

it('blocks SSRF targets (private addresses, bad schemes, credentials, unresolvable hosts)', function (string $url) {
    Http::fake();
    $device = pullDevice(['source_url' => $url]);

    $this->artisan('meteoview:fetch');

    expect($device->fresh()->last_error)->not->toBeNull();
    Http::assertNothingSent();
})->with(['https://internal.test/x', 'http://127.0.0.1/x', 'http://169.254.169.254/latest/meta-data', 'http://[::1]/x', 'file:///etc/passwd', 'ftp://api.example.test/x', 'https://user:pw@api.example.test/x', 'https://nxdomain.test/x']);

it('rejects unsafe source URLs when saving a device', function () {
    $this->actingAs(\App\Models\User::factory()->create())
        ->postJson('/api/devices', ['name' => 'X', 'source_url' => 'http://127.0.0.1/admin'])->assertUnprocessable()->assertJsonValidationErrors('source_url');
});

it('protects the cron endpoint with a secret', function () {
    Http::fake(['*' => Http::response(['current' => ['time' => '2026-10-17T10:00:00Z', 'temp' => 7]])]);
    $device = pullDevice();

    $this->getJson('/api/cron/fetch')->assertUnauthorized();
    $this->getJson('/api/cron/fetch', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
    $this->getJson('/api/cron/fetch?token=topsecret')->assertUnauthorized(); // secrets in URLs are not accepted
    expect(Measurement::count())->toBe(0);

    $this->getJson('/api/cron/fetch', ['X-Cron-Token' => 'topsecret'])->assertOk()->assertJson(['devices' => 1, 'ok' => 1, 'failed' => 0]);
    $this->assertDatabaseHas('measurements', ['device_id' => $device->id, 'value' => 7.0]);
});

it('refuses the cron endpoint when no secret is configured', function () {
    config(['services.cron.secret' => '']);

    $this->postJson('/api/cron/fetch', [], ['X-Cron-Token' => ''])->assertUnauthorized();
});

it('reports failures in the cron summary', function () {
    Http::fake(['*' => Http::response('x', 500)]);
    pullDevice();

    $this->postJson('/api/cron/fetch', [], ['Authorization' => 'Bearer topsecret'])->assertOk()->assertJson(['failed' => 1]);
});

it('throttles the cron endpoint', function () {
    foreach (range(1, 10) as $_) {
        $this->postJson('/api/cron/fetch', [], ['X-Cron-Token' => 'x']);
    }

    $this->postJson('/api/cron/fetch', [], ['X-Cron-Token' => 'x'])->assertStatus(429);
});
