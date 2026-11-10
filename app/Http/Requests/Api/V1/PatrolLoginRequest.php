<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class PatrolLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username'         => ['required', 'string', 'max:100'],
            'password'         => ['required', 'string'],
            'device_uuid'      => ['nullable', 'string', 'max:255'],
            'device_fcm_token' => ['nullable', 'string', 'max:500'],
        ];
    }
}
