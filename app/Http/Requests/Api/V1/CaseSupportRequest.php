<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CaseSupportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isPatrolOfficer();
    }

    public function rules(): array
    {
        return [
            'support_type'  => 'required|string|in:recovery_crane,medical_ambulance,additional_patrol,relief_handover,fire_brigade',
            'internal_note' => 'required|string|min:5|max:1000',
        ];
    }
}
