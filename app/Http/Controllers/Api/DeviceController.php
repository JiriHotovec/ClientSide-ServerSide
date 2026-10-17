<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Services\DeviceService;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(private DeviceService $devices) {}

    /** Users see only their own devices, administrators see all. */
    public function index(Request $request)
    {
        $user = $request->user();

        return DeviceResource::collection(
            Device::with('types')->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))->orderBy('name')->get()
        );
    }

    public function store(DeviceRequest $request)
    {
        $this->authorize('create', Device::class);

        return (new DeviceResource($this->devices->create($request->user(), $request->validated())->load('types')))
            ->response()->setStatusCode(201);
    }

    public function show(Device $device)
    {
        $this->authorize('update', $device);

        return new DeviceResource($device->load('types'));
    }

    public function update(DeviceRequest $request, Device $device)
    {
        $this->authorize('update', $device);

        return new DeviceResource($this->devices->update($device, $request->validated())->load('types'));
    }

    public function destroy(Device $device)
    {
        $this->authorize('delete', $device);
        $device->delete();

        return response()->noContent();
    }

    public function visibility(Request $request, Device $device)
    {
        $this->authorize('update', $device);
        $data = $request->validate(['is_public' => ['required', 'boolean']]);

        return new DeviceResource($this->devices->setVisibility($device, $data['is_public']));
    }

    /** Generates a new ingest token; the plain value is returned exactly once. */
    public function token(Device $device)
    {
        $this->authorize('update', $device);

        return response()->json(['token' => $device->regenerateIngestToken()]);
    }
}
