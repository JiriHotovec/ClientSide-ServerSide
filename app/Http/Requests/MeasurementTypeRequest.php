<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MeasurementTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policies are applied in the controller
    }

    /** Display rules are shared with the per-device override validation in DeviceRequest. */
    public static function displayRules(string $prefix = '', bool $nullable = false): array
    {
        $n = $nullable ? ['nullable'] : [];

        return [
            $prefix.'unit_position' => [...$n, Rule::in(['before', 'after', 'none'])],
            $prefix.'unit_space' => [...$n, 'boolean'],
            $prefix.'decimals' => [...$n, 'integer', 'min:0', 'max:6'],
            $prefix.'color' => [...$n, 'regex:/^#[0-9a-fA-F]{6}$/'],
            $prefix.'chart_type' => [...$n, Rule::in(['line', 'bar'])],
            $prefix.'y_min' => [...$n, 'numeric'],
            $prefix.'y_max' => [...$n, 'numeric', ...($prefix === '' ? ['gte:y_min'] : [])],
        ];
    }

    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $req = $creating ? 'required' : 'sometimes';

        return [
            'key' => $creating ? ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('measurement_types', 'key')] : ['prohibited'],
            'name' => [$req, 'string', 'max:80'],
            'unit' => [$req, 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_public' => ['sometimes', 'boolean'],
        ] + self::displayRules('', true);
    }

    protected function prepareForValidation(): void
    {
        foreach (['y_min', 'y_max', 'description', 'color'] as $f) {
            if ($this->has($f) && $this->input($f) === '') {
                $this->merge([$f => null]);
            }
        }
    }
}
