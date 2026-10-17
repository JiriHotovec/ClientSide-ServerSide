<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\MeasurementType;
use App\Services\MeasurementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Server-rendered administration pages. Data changes go through the REST API (see public/js/manage.js). */
class ManageController extends Controller
{
    private const PER_PAGE = 9;

    /** Admin panel: card overview. Own devices by default; administrators can switch to all devices. */
    public function overview(Request $request, MeasurementService $service)
    {
        $devices = $this->deviceQuery($request)->paginate(self::PER_PAGE)->withQueryString();
        $snaps = $devices->getCollection()->mapWithKeys(fn ($d) => [$d->id => $service->snapshot($d)]);

        return view('manage.overview', ['devices' => $devices, 'snaps' => $snaps] + $this->filters($request));
    }

    /** Device list incl. data source status. */
    public function devices(Request $request)
    {
        return view('manage.devices', ['devices' => $this->deviceQuery($request)->paginate(15)->withQueryString()] + $this->filters($request));
    }

    public function createDevice(Request $request)
    {
        return view('manage.device-form', ['device' => null, 'types' => MeasurementType::visibleTo($request->user())->orderBy('name')->get()]);
    }

    public function editDevice(Request $request, Device $device)
    {
        $this->authorize('update', $device);

        return view('manage.device-form', [
            'device' => $device->load('types'),
            'types' => MeasurementType::visibleTo($device->owner)->orderBy('name')->get(),
        ]);
    }

    public function types(Request $request)
    {
        $user = $request->user();
        $scope = $request->query('scope', 'available');
        $q = MeasurementType::query()->with('creator')
            ->when($scope === 'global', fn (Builder $b) => $b->where('is_public', true))
            ->when($scope === 'mine', fn (Builder $b) => $b->where('created_by', $user->id))
            ->when($scope === 'available' || ! in_array($scope, ['global', 'mine', 'all'], true), fn (Builder $b) => $b->visibleTo($user))
            ->when($scope === 'all' && ! $user->isAdmin(), fn (Builder $b) => $b->visibleTo($user))
            ->when($request->filled('q'), fn (Builder $b) => $b->where(fn ($w) => $w->where('name', 'like', '%'.$request->query('q').'%')->orWhere('key', 'like', '%'.$request->query('q').'%')))
            ->orderBy('name');

        return view('manage.types', ['types' => $q->paginate(15)->withQueryString(), 'scope' => $scope, 'search' => (string) $request->query('q', '')]);
    }

    public function createType()
    {
        return view('manage.type-form', ['type' => null]);
    }

    public function editType(MeasurementType $measurementType)
    {
        $this->authorize('update', $measurementType);

        return view('manage.type-form', ['type' => $measurementType]);
    }

    private function deviceQuery(Request $request): Builder
    {
        $user = $request->user();
        $scope = $user->isAdmin() && $request->query('scope') === 'all' ? 'all' : 'mine';

        return Device::query()->with(['types', 'owner'])
            ->when($scope === 'mine', fn (Builder $q) => $q->where('user_id', $user->id))
            ->when(in_array($request->query('visibility'), ['public', 'private'], true), fn (Builder $q) => $q->where('is_public', $request->query('visibility') === 'public'))
            ->when($request->filled('q'), fn (Builder $q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->query('q').'%')->orWhere('location', 'like', '%'.$request->query('q').'%')))
            ->when($request->query('source') === 'pull', fn (Builder $q) => $q->whereNotNull('source_url'))
            ->when($request->query('source') === 'push', fn (Builder $q) => $q->whereNull('source_url'))
            ->when($request->query('source') === 'error', fn (Builder $q) => $q->whereNotNull('last_error'))
            ->orderBy('name');
    }

    private function filters(Request $request): array
    {
        return [
            'scope' => $request->user()->isAdmin() && $request->query('scope') === 'all' ? 'all' : 'mine',
            'search' => (string) $request->query('q', ''),
            'visibility' => (string) $request->query('visibility', ''),
            'source' => (string) $request->query('source', ''),
        ];
    }
}
