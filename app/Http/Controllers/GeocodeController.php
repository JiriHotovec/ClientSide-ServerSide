<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Server-side proxy for OpenStreetMap's Nominatim: keeps the browser on our own origin (strict CSP), sends the
 * identifying User-Agent the usage policy asks for, and caches answers so the public service is not hammered.
 */
class GeocodeController extends Controller
{
    private const BASE = 'https://nominatim.openstreetmap.org';

    public function search(Request $request): JsonResponse
    {
        $q = $request->validate(['q' => ['required', 'string', 'min:2', 'max:120']])['q'];

        $results = $this->remember('search', mb_strtolower($q), fn () => $this->call('/search', ['q' => $q, 'format' => 'jsonv2', 'limit' => 5]));
        if ($results === null) {
            return $this->unavailable();
        }

        return response()->json(['data' => collect($results)->map(fn ($r) => [
            'name' => $r['display_name'] ?? '', 'lat' => (float) $r['lat'], 'lng' => (float) $r['lon'],
        ])->values()]);
    }

    public function reverse(Request $request): JsonResponse
    {
        $d = $request->validate(['lat' => ['required', 'numeric', 'between:-90,90'], 'lng' => ['required', 'numeric', 'between:-180,180']]);
        // Rounded to ~1 km so nearby clicks share one cached answer.
        $lat = round((float) $d['lat'], 2);
        $lng = round((float) $d['lng'], 2);

        $place = $this->remember('reverse', "$lat,$lng", fn () => $this->call('/reverse', ['lat' => $lat, 'lon' => $lng, 'format' => 'jsonv2', 'zoom' => 12, 'addressdetails' => 1]));
        if ($place === null) {
            return $this->unavailable();
        }

        $a = $place['address'] ?? [];
        $town = $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['hamlet'] ?? $a['municipality'] ?? $a['county'] ?? null;

        return response()->json(['name' => collect([$town, $a['country'] ?? null])->filter()->implode(', ') ?: ($place['display_name'] ?? '')]);
    }

    /** @return array<mixed>|null null when the map service failed (failures are not cached) */
    private function remember(string $kind, string $key, \Closure $fetch): ?array
    {
        $cacheKey = "geocode:$kind:".app()->getLocale().':'.sha1($key);
        if (($hit = Cache::get($cacheKey)) !== null) {
            return $hit;
        }
        $value = $fetch();
        if ($value !== null) {
            Cache::put($cacheKey, $value, now()->addDay());
        }

        return $value;
    }

    /** @return array<mixed>|null */
    private function call(string $path, array $query): ?array
    {
        try {
            $response = Http::timeout(6)->acceptJson()
                ->withHeaders(['User-Agent' => 'MeteoView/1.0 ('.config('app.url').')', 'Accept-Language' => app()->getLocale()])
                ->get(self::BASE.$path, $query);
        } catch (\Throwable) {
            return null;
        }

        return $response->successful() && is_array($response->json()) ? $response->json() : null;
    }

    private function unavailable(): JsonResponse
    {
        return response()->json(['message' => __('The map service is not available right now. Try again later.')], 502);
    }
}
