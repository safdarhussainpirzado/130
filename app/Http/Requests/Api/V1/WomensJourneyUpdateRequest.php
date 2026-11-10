<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class WomensJourneyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isCommuter();
    }

    public function rules(): array
    {
        return [
            'update_type'   => 'required|string|in:check_in,note,silent_alert,safe_arrival',
            'content'       => 'nullable|string|max:1000',
            'location_name' => 'nullable|string|max:150',
            'latitude'      => 'nullable|numeric|between:-90,90',
            'longitude'     => 'nullable|numeric|between:-180,180',
            'metadata'      => 'nullable|array',
        ];
    }
}
