<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class PatrolStatusUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isPatrolOfficer();
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:AVAILABLE,BUSY',
        ];
    }
}
