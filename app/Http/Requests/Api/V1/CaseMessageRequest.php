<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CaseMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'message_type' => 'required|string|in:text,location,image,voice_note',
            'audience'     => 'nullable|string|in:shared_case,internal_control',
            'content'      => 'nullable|string|max:2000',
            'media_url'    => 'nullable|string|max:1000',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'metadata'     => 'nullable|array',
        ];
    }
}
