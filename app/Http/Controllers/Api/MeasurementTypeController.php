<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeasurementType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeasurementTypeController extends Controller
{
    public function index()
    {
        return response()->json(['data' => MeasurementType::orderBy('name')->get(['id', 'key', 'name', 'unit', 'description', 'is_system', 'created_by'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('measurement_types', 'key')],
            'name' => ['required', 'string', 'max:80'],
            'unit' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $type = new MeasurementType($data);
        $type->created_by = $request->user()->id;
        $type->save();

        return response()->json(['data' => $type], 201);
    }

    public function update(Request $request, MeasurementType $measurementType)
    {
        $this->authorize('update', $measurementType);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $measurementType->update($data);

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
