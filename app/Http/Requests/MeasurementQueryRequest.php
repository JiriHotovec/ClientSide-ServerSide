<?php

namespace App\Http\Requests;

use App\Services\MeasurementService;
use App\Support\TimeRange;
use Illuminate\Contracts\Validation\Validator;
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
            'tz' => ['nullable', 'integer', 'between:-840,840'],
            'from' => ['nullable', 'date', 'required_with:to'],
            'to' => ['nullable', 'date', 'required_with:from', 'after_or_equal:from'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isEmpty() && filled($this->query('from')) && filled($this->query('to'))) {
                if (TimeRange::custom($this->query('from'), $this->query('to'), (int) $this->query('tz', 0))->days() > TimeRange::MAX_DAYS) {
                    $v->errors()->add('to', 'The date range can cover at most '.TimeRange::MAX_DAYS.' days.');
                }
            }
        }];
    }

    public function range(): TimeRange
    {
        return TimeRange::fromInput($this->query('period'), $this->query('from'), $this->query('to'), (int) $this->query('tz', 0));
    }
}
