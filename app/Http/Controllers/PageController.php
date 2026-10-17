<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\MeasurementService;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function welcome(MeasurementService $service)
    {
        $featured = Device::public()->with('types')->orderBy('id')->get()
            ->map(fn ($d) => ['device' => $d, 'snap' => $service->snapshot($d)])
            ->first(fn ($x) => $x['snap'] && $x['snap']['latest'] !== null);

        return view('welcome', ['featured' => $featured]);
    }

    public function publicDevices(MeasurementService $service)
    {
        $search = trim((string) request('q', ''));
        $devices = Device::public()->with('types')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('location', 'like', "%{$search}%")))
            ->orderBy('name')->paginate(9)->withQueryString();

        return view('devices.index', ['devices' => $devices, 'search' => $search, 'snaps' => $devices->getCollection()->mapWithKeys(fn ($d) => [$d->id => $service->snapshot($d)])]);
    }

    public function publicDevice(Device $device)
    {
        abort_unless($device->is_public, 404); // private devices look non-existent on public pages

        return view('devices.show', ['device' => $device->load('types')]);
    }

    /** Personal dashboard (standard users only; admins are blocked by the role middleware). */
    public function dashboard(Request $request)
    {
        $devices = $request->user()->devices()->with('types')->orderBy('name')->get();

        return view('dashboard', ['devices' => $devices, 'selected' => $devices->first()]);
    }

    /** htmx fragment: dashboard panel for one owned device. */
    public function dashboardPanel(Request $request, Device $device)
    {
        abort_unless($device->user_id === $request->user()->id, 403);

        return view('partials.device-panel', [
            'device' => $device->load('types'),
            'endpoint' => url("/api/devices/{$device->id}/measurements"),
        ]);
    }
}
