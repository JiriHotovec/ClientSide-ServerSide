<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\IngestService;
use Illuminate\Http\Request;

/** Publish own data: POST /api/ingest with "Authorization: Bearer <device token>". */
class IngestController extends Controller
{
    public function __invoke(Request $request, IngestService $ingest)
    {
        $token = $request->bearerToken();
        $device = $token ? Device::where('ingest_token_hash', Device::hashToken($token))->first() : null;
        abort_unless($device, 401, 'Invalid or missing device token.');

        $data = $request->validate([
            'measurements' => ['required', 'array', 'min:1', 'max:500'],
            'measurements.*.type' => ['required', 'string', 'max:40'],
            'measurements.*.value' => ['present', 'nullable', 'numeric'],
            'measurements.*.measured_at' => ['nullable', 'date'],
        ]);

        $readings = array_map(fn ($m) => [
            'type' => $m['type'],
            'value' => $m['value'] === null ? null : (float) $m['value'],
            'measured_at' => $m['measured_at'] ?? now(),
        ], $data['measurements']);

        $accepted = $ingest->store($device, $readings, 'ingest');

        return response()->json(['accepted' => $accepted, 'rejected' => count($readings) - $accepted], 201);
    }
}
