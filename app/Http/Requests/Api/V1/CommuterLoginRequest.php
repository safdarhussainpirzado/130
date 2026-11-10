<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CommuterLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'            => ['required', 'string', 'email'],
            'password'         => ['required', 'string'],
            'device_fcm_token' => ['nullable', 'string', 'max:500'],
        ];
    }
}
