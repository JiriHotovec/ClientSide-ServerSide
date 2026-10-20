<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeasurementQueryRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Services\MeasurementService;
use Illuminate\Http\Request;

/** Public, unauthenticated API. Private devices are indistinguishable from non-existent ones (404). */
class PublicDeviceController extends Controller
{
    public function index(Request $request)
    {
        // Public resource only: the owner-only block is gated on the request user, so guests never get it.
        return DeviceResource::collection(Device::public()->with('types', 'charts.type')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->query('q').'%')->orWhere('location', 'like', '%'.$request->query('q').'%')))
            ->orderBy('name')->get());
    }

    public function show(string $id)
    {
        return new DeviceResource(Device::public()->with('types', 'charts.type')->where('uuid', $id)->firstOrFail());
    }

    public function measurements(string $id, MeasurementQueryRequest $request, MeasurementService $service)
    {
        $device = Device::public()->with('types', 'charts.type')->where('uuid', $id)->firstOrFail();

        return MeasurementController::respond($device, $request, $service);
    }
}
