<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CommuterRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'        => ['required', 'string', 'min:2', 'max:100'],
            'email'            => ['required', 'string', 'email:rfc', 'max:150', 'unique:users,email'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
            'device_fcm_token' => ['nullable', 'string', 'max:500'],
        ];
    }
}
