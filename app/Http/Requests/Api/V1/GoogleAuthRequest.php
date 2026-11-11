<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class GoogleAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_token'         => ['required', 'string', 'min:30'],
            'device_fcm_token' => ['nullable', 'string', 'max:500'],
        ];
    }
}
