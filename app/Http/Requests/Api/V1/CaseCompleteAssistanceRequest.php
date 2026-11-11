<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CaseCompleteAssistanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isPatrolOfficer();
    }

    public function rules(): array
    {
        return [
            'outcome'               => 'required|string|max:50',
            'public_summary'        => 'required|string|min:5|max:1000',
            'internal_control_note' => 'nullable|string|max:2000',
        ];
    }
}
