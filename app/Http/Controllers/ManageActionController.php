<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceRequest;
use App\Http\Requests\MeasurementTypeRequest;
use App\Models\Device;
use App\Models\MeasurementType;
use App\Services\DeviceService;
use App\Services\PullService;
use App\Support\SourceMapping;
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

        return redirect()->route('manage.devices')->with('status', __('Device ":name" was created.', ['name' => $device->name]));
    }

    public function updateDevice(DeviceRequest $request, Device $device)
    {
        $this->authorize('update', $device);
        $this->devices->update($device, $request->validated());

        return redirect()->route('manage.devices')->with('status', __('Device ":name" was updated.', ['name' => $device->name]));
    }

    public function destroyDevice(Device $device)
    {
        $this->authorize('delete', $device);
        $device->delete();

        return redirect()->route('manage.devices')->with('status', __('Device ":name" was deleted.', ['name' => $device->name]));
    }

    public function visibility(Request $request, Device $device)
    {
        $this->authorize('update', $device);
        $data = $request->validate(['is_public' => ['required', 'boolean']]);
        $this->devices->setVisibility($device, $data['is_public']);

        return back()->with('status', __('":name" is now :state.', ['name' => $device->name, 'state' => $device->is_public ? __('public') : __('private')]));
    }

    /** The plain token is flashed once; only its hash is stored. */
    public function token(Request $request, Device $device)
    {
        $this->authorize('update', $device);
        $token = $device->regenerateIngestToken();

        // From the device form the token appears in place (htmx); from the list it is flashed.
        if ($request->header('HX-Target') === 'token-box') {
            return view('manage.device._token', ['device' => $device, 'token' => $token]);
        }

        return back()->with('status', __('New token for ":name" (shown only once, copy it now): :token. Send readings with "Authorization: Bearer <token>" to POST /api/ingest.', ['name' => $device->name, 'token' => $token]));
    }

    /** htmx: try the pull source typed into the device form without saving anything. */
    public function testSource(Request $request, PullService $pull)
    {
        $url = (string) $request->input('source_url');
        $valid = filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url);
        $keys = MeasurementType::visibleTo($request->user())->whereIn('id', (array) $request->input('types', []))->pluck('key', 'id')->all();
        $raw = $request->input('source_mapping');
        $mapping = filled($raw) ? json_decode((string) $raw, true) : SourceMapping::fromForm($request->all(), $keys);

        if (! $valid) {
            $result = ['ok' => false, 'error' => __('Enter a valid http(s) address first.')];
        } elseif (! is_array($mapping) || empty($mapping['values'])) {
            $result = ['ok' => false, 'error' => __('Add the path of at least one dataset first.')];
        } else {
            $result = $pull->preview($url, $mapping);
        }

        return view('manage.device._source-preview', [
            'result' => $result,
            'types' => MeasurementType::visibleTo($request->user())->whereIn('key', array_keys($mapping['values'] ?? []))->get()->keyBy('key'),
        ]);
    }

    public function storeType(MeasurementTypeRequest $request)
    {
        $type = new MeasurementType($request->validated());
        $type->created_by = $request->user()->id;
        $type->is_public = $request->user()->isAdmin() && $request->boolean('is_public'); // only admins publish globally
        $type->save();

        return redirect()->route('manage.types')->with('status', __('Measurement type ":name" was created.', ['name' => $type->name]));
    }

    public function updateType(MeasurementTypeRequest $request, MeasurementType $measurementType)
    {
        $this->authorize('update', $measurementType);
        $measurementType->update($request->validated());
        if ($request->user()->isAdmin()) {
            $measurementType->is_public = $request->boolean('is_public');
            $measurementType->save();
        }

        return redirect()->route('manage.types')->with('status', __('Measurement type ":name" was updated.', ['name' => $measurementType->name]));
    }

    public function destroyType(MeasurementType $measurementType)
    {
        $this->authorize('delete', $measurementType);
        if ($measurementType->isInUse()) {
            return back()->with('status', __('":name" is in use by a device and cannot be deleted.', ['name' => $measurementType->name]));
        }
        $measurementType->delete();

        return redirect()->route('manage.types')->with('status', __('Measurement type ":name" was deleted.', ['name' => $measurementType->name]));
    }
}
