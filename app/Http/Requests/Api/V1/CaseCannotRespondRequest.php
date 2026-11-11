<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CaseCannotRespondRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isPatrolOfficer();
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:5|max:1000',
        ];
    }
}
