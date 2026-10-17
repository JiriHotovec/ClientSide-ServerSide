<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeasurementTypeRequest;
use App\Models\MeasurementType;
use Illuminate\Http\Request;

class MeasurementTypeController extends Controller
{
    /** Global types plus the caller's own private ones (admins: everything). */
    public function index(Request $request)
    {
        return response()->json(['data' => MeasurementType::visibleTo($request->user())->orderBy('name')->get()]);
    }

    public function store(MeasurementTypeRequest $request)
    {
        $type = new MeasurementType($request->validated());
        $type->created_by = $request->user()->id;
        // Only administrators can publish a type globally; everybody else creates private types.
        $type->is_public = $request->user()->isAdmin() && $request->boolean('is_public');
        $type->save();

        return response()->json(['data' => $type->refresh()], 201);
    }

    public function update(MeasurementTypeRequest $request, MeasurementType $measurementType)
    {
        $this->authorize('update', $measurementType);
        $measurementType->update($request->validated());
        if ($request->user()->isAdmin() && $request->has('is_public')) {
            $measurementType->is_public = $request->boolean('is_public');
            $measurementType->save();
        }

        return response()->json(['data' => $measurementType]);
    }

    public function destroy(MeasurementType $measurementType)
    {
        $this->authorize('delete', $measurementType);
        if ($measurementType->isInUse()) {
            return response()->json(['message' => 'This measurement type is in use and cannot be deleted.'], 409);
        }
        $measurementType->delete();

        return response()->noContent();
    }
}
