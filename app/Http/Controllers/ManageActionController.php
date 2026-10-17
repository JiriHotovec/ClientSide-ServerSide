<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceRequest;
use App\Http\Requests\MeasurementTypeRequest;
use App\Models\Device;
use App\Models\MeasurementType;
use App\Services\DeviceService;
use Illuminate\Http\Request;

/**
 * Form actions of the workspace. Forms are enhanced with htmx (hx-boost): the server answers with a
 * redirect and htmx swaps in the resulting page. Without JavaScript the same flow works as plain POSTs.
 * The JSON REST API (routes/rest.php) uses the same services and policies.
 */
class ManageActionController extends Controller
{
    public function __construct(private DeviceService $devices) {}

    public function storeDevice(DeviceRequest $request)
    {
        $this->authorize('create', Device::class);
        $device = $this->devices->create($request->user(), $request->validated());

        return redirect()->route('manage.devices')->with('status', "Device \"{$device->name}\" was created.");
    }

    public function updateDevice(DeviceRequest $request, Device $device)
    {
        $this->authorize('update', $device);
        $this->devices->update($device, $request->validated());

        return redirect()->route('manage.devices')->with('status', "Device \"{$device->name}\" was updated.");
    }

    public function destroyDevice(Device $device)
    {
        $this->authorize('delete', $device);
        $device->delete();

        return redirect()->route('manage.devices')->with('status', "Device \"{$device->name}\" was deleted.");
    }

    public function visibility(Request $request, Device $device)
    {
        $this->authorize('update', $device);
        $data = $request->validate(['is_public' => ['required', 'boolean']]);
        $this->devices->setVisibility($device, $data['is_public']);

        return back()->with('status', "\"{$device->name}\" is now ".($device->is_public ? 'public' : 'private').'.');
    }

    /** The plain token is flashed once; only its hash is stored. */
    public function token(Device $device)
    {
        $this->authorize('update', $device);

        return back()->with('status', "New token for \"{$device->name}\" (shown only once, copy it now): ".$device->regenerateIngestToken()
            .'. Send readings with "Authorization: Bearer <token>" to POST /api/ingest.');
    }

    public function storeType(MeasurementTypeRequest $request)
    {
        $type = new MeasurementType($request->validated());
        $type->created_by = $request->user()->id;
        $type->is_public = $request->user()->isAdmin() && $request->boolean('is_public'); // only admins publish globally
        $type->save();

        return redirect()->route('manage.types')->with('status', "Measurement type \"{$type->name}\" was created.");
    }

    public function updateType(MeasurementTypeRequest $request, MeasurementType $measurementType)
    {
        $this->authorize('update', $measurementType);
        $measurementType->update($request->validated());
        if ($request->user()->isAdmin()) {
            $measurementType->is_public = $request->boolean('is_public');
            $measurementType->save();
        }

        return redirect()->route('manage.types')->with('status', "Measurement type \"{$measurementType->name}\" was updated.");
    }

    public function destroyType(MeasurementType $measurementType)
    {
        $this->authorize('delete', $measurementType);
        if ($measurementType->isInUse()) {
            return back()->with('status', "\"{$measurementType->name}\" is in use by a device and cannot be deleted.");
        }
        $measurementType->delete();

        return redirect()->route('manage.types')->with('status', "Measurement type \"{$measurementType->name}\" was deleted.");
    }
}
