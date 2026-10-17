<?php

namespace App\Http\Requests;

use App\Support\UrlGuard;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization is done by DevicePolicy in the controller
    }

    public function rules(): array
    {
        $req = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$req, 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:100'],
            'is_public' => ['sometimes', 'boolean'],
            'types' => ['sometimes', 'array'],
            'types.*' => ['integer', Rule::exists('measurement_types', 'id')],
            'type_settings' => ['sometimes', 'array'],
            'type_settings.*.unit' => ['nullable', 'string', 'max:20'],
        ] + MeasurementTypeRequest::displayRules('type_settings.*.', true) + [
            'source_url' => ['nullable', 'url:http,https', 'max:500'],
            'source_mapping' => ['nullable', 'array'],
            'source_mapping.values' => ['required_with:source_mapping', 'array'],
            'source_mapping.values.*' => ['string', 'max:200'],
            'source_mapping.items_path' => ['nullable', 'string', 'max:200'],
            'source_mapping.timestamp' => ['nullable', 'string', 'max:200'],
            'fetch_interval_min' => ['sometimes', 'integer', 'min:1', 'max:1440'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // HTML form (htmx) shape: unchecked checkboxes are absent, per-type overrides arrive as ts[id][field].
        if ($this->has('_form')) {
            $types = array_map('intval', (array) $this->input('types', []));
            $defaults = \App\Models\MeasurementType::whereIn('id', $types)->pluck('color', 'id');
            $settings = [];
            foreach ($types as $id) {
                $row = collect((array) $this->input("ts.$id", []))
                    ->filter(fn ($v) => $v !== null && $v !== '')
                    ->reject(fn ($v, $k) => $k === 'color' && strtolower($v) === strtolower($defaults[$id] ?? '#0077b6'));
                $settings[$id] = $row->all();
            }
            $this->merge(['types' => $types, 'type_settings' => $settings, 'is_public' => $this->boolean('is_public')]);
        }

        // Allow the HTML form to send the mapping as a JSON string.
        if (is_string($this->input('source_mapping'))) {
            $decoded = json_decode($this->input('source_mapping'), true);
            $this->merge(['source_mapping' => $this->input('source_mapping') === '' ? null : ($decoded ?? 'invalid')]);
        }
        foreach (['source_url', 'description', 'location'] as $f) {
            if ($this->input($f) === '') {
                $this->merge([$f => null]);
            }
        }
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($this->input('source_mapping') === 'invalid') {
                $v->errors()->add('source_mapping', 'The mapping must be valid JSON.');
            }
            // Types must be visible to the device owner (global ones or the owner's private ones).
            $owner = $this->route('device')?->owner ?? $this->user();
            $allowed = \App\Models\MeasurementType::visibleTo($owner)->whereIn('id', $this->input('types', []))->pluck('id')->all();
            foreach ($this->input('types', []) as $i => $id) {
                if (! in_array($id, $allowed)) {
                    $v->errors()->add("types.$i", 'This measurement type is not available for the device owner.');
                }
            }
            if ($url = $this->input('source_url')) {
                try {
                    app(UrlGuard::class)->check($url);
                } catch (\InvalidArgumentException $e) {
                    $v->errors()->add('source_url', $e->getMessage());
                }
            }
        }];
    }
}
