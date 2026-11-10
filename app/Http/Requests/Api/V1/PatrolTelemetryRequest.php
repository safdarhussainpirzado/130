<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class PatrolTelemetryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isPatrolOfficer();
    }

    public function rules(): array
    {
        return [
            'duty_session_id'   => 'nullable|string',
            'latitude'          => 'required|numeric|between:-90,90',
            'longitude'         => 'required|numeric|between:-180,180',
            'speed_kph'         => 'required|numeric|min:0|max:300',
            'bearing_degrees'   => 'required|numeric|min:0|max:360',
            'carriageway'       => 'nullable|string|max:100',
            'accuracy_meters'   => 'nullable|numeric|min:0',
            'timestamp'         => 'nullable|date',
        ];
    }
}
