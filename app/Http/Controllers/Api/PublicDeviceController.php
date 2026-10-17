<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeasurementQueryRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Services\MeasurementService;

/** Public, unauthenticated API. Private devices are indistinguishable from non-existent ones (404). */
class PublicDeviceController extends Controller
{
    public function index()
    {
        // Public resource only: the owner-only block is gated on the request user, so guests never get it.
        return DeviceResource::collection(Device::public()->with('types')->orderBy('name')->get());
    }

    public function show(string $id)
    {
        return new DeviceResource(Device::public()->with('types')->where('uuid', $id)->firstOrFail());
    }

    public function measurements(string $id, MeasurementQueryRequest $request, MeasurementService $service)
    {
        $device = Device::public()->with('types')->where('uuid', $id)->firstOrFail();

        return MeasurementController::respond($device, $request, $service);
    }
}
