<?php

namespace App\Services;

use App\Models\Device;
use App\Support\UrlGuard;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pulls data for a device from its external JSON endpoint.
 *
 * source_mapping format:
 *   { "items_path": "hourly" (optional), "timestamp": "current.time" (optional),
 *     "values": { "<measurement type key>": "<dot path>" } }
 */
class PullService
{
    private const MAX_BYTES = 1_000_000;

    public function __construct(private UrlGuard $guard, private IngestService $ingest) {}

    public function isDue(Device $device): bool
    {
        return $device->source_url
            && (! $device->last_fetched_at || $device->last_fetched_at->lte(now()->subMinutes($device->fetch_interval_min)));
    }

    /** Pulls every due device (or one specific device); one failing device never stops the others. */
    public function run(?string $deviceId = null, bool $force = false): array
    {
        return Device::query()
            ->whereNotNull('source_url')
            ->when($deviceId, fn ($q) => $q->where('uuid', $deviceId))
            ->get()
            ->filter(fn (Device $d) => $force || $deviceId || $this->isDue($d))
            ->map(fn (Device $d) => $this->pull($d))
            ->values()
            ->all();
    }

    /**
     * Downloads and decodes the JSON of a source. The connection is pinned to the vetted IP (SSRF guard).
     *
     * @return array<mixed>
     */
    public function fetchJson(string $url): array
    {
        [$host, $port, $ip] = $this->guard->check($url);
        $response = Http::timeout(10)->retry(2, 200, throw: false)->withoutRedirecting()
            ->withOptions(['curl' => [CURLOPT_RESOLVE => ["$host:$port:$ip"]]])
            ->acceptJson()->get($url);

        if ($response->failed()) {
            throw new \RuntimeException('Data source responded with HTTP '.$response->status().'.');
        }
        if (strlen($response->body()) > self::MAX_BYTES) {
            throw new \RuntimeException('Data source response is too large.');
        }
        $json = $response->json();
        if (! is_array($json)) {
            throw new \RuntimeException('Data source did not return valid JSON.');
        }

        return $json;
    }

    /**
     * Try a source without storing anything (the "Test fetch" button of the device form).
     *
     * @return array{ok: bool, error?: string, readings?: list<array{type: string, value: ?float, measured_at: Carbon}>}
     */
    public function preview(string $url, array $mapping): array
    {
        try {
            return ['ok' => true, 'readings' => array_slice($this->map($mapping, $this->fetchJson($url)), 0, 30)];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => self::translateError($this->errorKey($e))];
        }
    }

    /** Stored errors are English keys; this shows them in the viewer's language. */
    public static function translateError(string $message): string
    {
        if (preg_match('/^Data source responded with HTTP (\d+)\.$/', $message, $m)) {
            return __('Data source responded with HTTP :status.', ['status' => $m[1]]);
        }

        return __($message);
    }

    private function errorKey(Throwable $e): string
    {
        return $e instanceof \InvalidArgumentException || $e instanceof \RuntimeException ? $e->getMessage() : 'Data source is unavailable.';
    }

    /** Never throws: failures are stored on the device (last_error) so the UI can explain them. */
    public function pull(Device $device): array
    {
        try {
            $json = $this->fetchJson($device->source_url);

            $stored = $this->ingest->store($device, $this->map($device->source_mapping ?? [], $json), 'pull');
            $device->forceFill(['last_fetched_at' => now(), 'last_error' => null])->save();

            return ['device' => $device->uuid, 'ok' => true, 'stored' => $stored];
        } catch (Throwable $e) {
            $message = $this->errorKey($e);
            $device->forceFill(['last_fetched_at' => now(), 'last_error' => mb_substr($message, 0, 500)])->save();

            return ['device' => $device->uuid, 'ok' => false, 'error' => $message];
        }
    }

    /** @return list<array{type:string,value:?float,measured_at:Carbon}> */
    public function map(array $mapping, array $json): array
    {
        $values = $mapping['values'] ?? [];
        $items = isset($mapping['items_path']) ? (Arr::get($json, $mapping['items_path']) ?? []) : [$json];
        $out = [];

        foreach ($items as $item) {
            $at = isset($mapping['timestamp']) && Arr::get($item, $mapping['timestamp'])
                ? Carbon::parse(Arr::get($item, $mapping['timestamp']))->utc()
                : now()->startOfMinute();

            foreach ($values as $typeKey => $path) {
                $raw = Arr::get($item, $path);
                $out[] = [
                    'type' => $typeKey,
                    'value' => is_numeric($raw) ? (float) $raw : null, // missing/garbage stays null, never 0
                    'measured_at' => $at,
                ];
            }
        }

        return $out;
    }
}
