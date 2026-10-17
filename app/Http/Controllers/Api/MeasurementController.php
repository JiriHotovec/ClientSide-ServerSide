<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeasurementQueryRequest;
use App\Models\Device;
use App\Services\MeasurementService;

class MeasurementController extends Controller
{
    public function __invoke(Device $device, MeasurementQueryRequest $request, MeasurementService $service)
    {
        $this->authorize('view', $device);

        return self::respond($device->load('types'), $request, $service);
    }

    public static function respond(Device $device, MeasurementQueryRequest $request, MeasurementService $service)
    {
        $type = $request->filled('type')
            ? $device->types->firstWhere('key', $request->query('type'))
            : $device->types->first();

        if (! $type) {
            return response()->json(['message' => 'This device has no such dataset.'], 404);
        }

        return response()->json($service->series($device, $type, $request->range()));
    }
}
