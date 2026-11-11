<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommuterUpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'full_name'                          => ['nullable', 'string', 'min:2', 'max:100'],
            'mobile_no'                          => [
                'nullable', 
                'string', 
                'regex:/^(03[0-9]{9}|\+923[0-9]{9})$/',
                Rule::unique('users', 'mobile_no')->ignore($userId),
            ],
            'gender'                             => ['nullable', 'in:female,male,prefer_not_to_say'],
            'cnic'                               => [
                'nullable', 
                'string', 
                'regex:/^[0-9]{5}-[0-9]{7}-[0-9]$/',
                Rule::unique('users', 'cnic')->ignore($userId),
            ],
            'blood_group'                        => ['nullable', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'preferred_language'                 => ['nullable', 'in:ur,en'],
            'emergency_contacts'                 => ['nullable', 'array', 'max:5'],
            'emergency_contacts.*.name'          => ['required_with:emergency_contacts', 'string', 'max:100'],
            'emergency_contacts.*.phone'         => ['required_with:emergency_contacts', 'string', 'max:20'],
            'emergency_contacts.*.relationship'  => ['nullable', 'string', 'max:50'],
            'primary_vehicle'                    => ['nullable', 'array'],
            'primary_vehicle.registration_no'    => ['required_with:primary_vehicle', 'string', 'max:20'],
            'primary_vehicle.make_model'         => ['nullable', 'string', 'max:50'],
            'primary_vehicle.color'              => ['nullable', 'string', 'max:30'],
            'primary_vehicle.type'               => ['nullable', 'string', 'in:car,bike,jeep,van,bus,truck,other'],
        ];
    }
}
