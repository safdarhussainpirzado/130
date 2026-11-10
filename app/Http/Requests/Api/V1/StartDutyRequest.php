<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StartDutyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isPatrolOfficer();
    }

    public function rules(): array
    {
        return [
            'vehicle_callsign'     => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z0-9\-_]{2,20}$/'],
            'vehicle_reg_no'       => ['nullable', 'string', 'max:50'],
            'assisting_officer_id' => ['nullable', 'integer', 'exists:users,id'],
            'beat_office_id'       => ['required', 'integer', 'exists:offices,id'],
            'initial_latitude'     => ['nullable', 'numeric', 'between:-90,90'],
            'initial_longitude'    => ['nullable', 'numeric', 'between:-180,180'],
            'gps_accuracy_meters'  => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_callsign.regex' => 'The vehicle callsign must consist of letters, numbers, hyphens, or underscores (e.g., P-12, MT-04).',
        ];
    }
}
