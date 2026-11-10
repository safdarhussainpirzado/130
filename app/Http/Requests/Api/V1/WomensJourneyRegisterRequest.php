<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class WomensJourneyRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isCommuter();
    }

    public function rules(): array
    {
        return [
            'origin_city'        => 'required|string|max:100',
            'origin_stop'        => 'required|string|max:150',
            'destination_city'   => 'required|string|max:100',
            'destination_stop'   => 'required|string|max:150',
            'psv_company_name'   => 'required|string|max:150',
            'psv_reg_no'         => 'required|string|max:50',
            'psv_seat_no'        => 'nullable|string|max:20',
            'emergency_contacts' => 'nullable|array',
            'notes'              => 'nullable|string|max:500',
        ];
    }
}
