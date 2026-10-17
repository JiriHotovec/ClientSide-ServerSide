<?php

namespace App\Http\Requests;

use App\Services\MeasurementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MeasurementQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['sometimes', Rule::in(array_keys(MeasurementService::PERIODS))],
            'type' => ['sometimes', 'string', 'max:40'],
        ];
    }
}
