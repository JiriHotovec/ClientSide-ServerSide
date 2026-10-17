<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\MeasurementService;
use App\Support\TimeRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PageController extends Controller
{
    public function welcome(MeasurementService $service): View
    {
        $stations = Device::public()->with('types')->orderBy('id')->get()
            ->map(fn (Device $device): array => ['device' => $device, 'snap' => $service->snapshot($device)]);
        $featured = $stations->first(fn ($station) => $station['snap'] && $station['snap']['latest'] !== null);

        return view('welcome', ['featured' => $featured, 'stations' => $stations->take(3)]);
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
            'endpoint' => url("/api/devices/{$device->uuid}/measurements"),
        ]);
    }

    /** htmx popup: aggregated data table of one dataset. Same visibility rules as the dashboards (private => 404 for outsiders). */
    public function dataTable(Request $request, Device $device, string $type, MeasurementService $service)
    {
        abort_unless(Gate::allows('view', $device), 404);
        $device->load('types');
        $measurementType = $device->types->firstWhere('key', $type);
        abort_unless($measurementType, 404);

        $period = (string) $request->query('period', '24h');
        abort_unless($period === 'custom' || array_key_exists($period, MeasurementService::PERIODS), 422);
        $tz = (int) $request->query('tz', 0);
        abort_unless($tz >= -840 && $tz <= 840, 422);

        if ($period === 'custom') {
            // Default to the last 7 days (local time) when no dates are given yet.
            $localNow = now()->subMinutes($tz);
            $from = $request->query('from') ?: $localNow->copy()->subDays(7)->format('Y-m-d\TH:i');
            $to = $request->query('to') ?: $localNow->format('Y-m-d\TH:i');
            abort_unless(strtotime((string) $from) !== false && strtotime((string) $to) !== false && strtotime($from) <= strtotime($to), 422);
            $range = TimeRange::custom($from, $to, $tz);
            abort_if($range->days() > TimeRange::MAX_DAYS, 422);
        } else {
            [$from, $to] = [null, null];
            $range = TimeRange::preset($period);
        }

        $bucket = (string) $request->query('bucket', $range->defaultBucket());
        abort_unless(array_key_exists($bucket, MeasurementService::BUCKETS), 422);

        return view('partials.data-popup', [
            'device' => $device, 'type' => $measurementType, 'period' => $period, 'bucketKey' => $bucket,
            'from' => $from, 'to' => $to,
            'tz' => $tz,
            'agg' => $service->aggregate($device, $measurementType, $range, $bucket, $tz),
        ]);
    }
}
