<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreOrganizationSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isClinic = $this->input('org_type') === 'clinic';
        $isCorporate = $this->input('org_type') === 'corporate';

        return [
            'org_type' => ['required', 'in:university,corporate,clinic'],
            'access_model' => ['required', 'in:closed,open'],
            'org_name' => ['required', 'string', 'min:2', 'max:255'],
            'org_size' => [$isClinic ? 'nullable' : 'nullable', 'string'],
            'ssm_number' => [
                $isCorporate ? 'required' : 'nullable',
                'regex:/^\d{12}$/',
            ],

            'location.name' => [$isClinic ? 'required' : 'nullable', 'string', 'max:255'],
            'location.address' => [$isClinic ? 'required' : 'nullable', 'string'],
            'location.city' => [$isClinic ? 'required' : 'nullable', 'string', 'max:255'],
            'location.state' => [$isClinic ? 'required' : 'nullable', 'string', 'max:255'],
            'location.postcode' => [$isClinic ? 'required' : 'nullable', 'string', 'max:20'],
            'location.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'location.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photos' => [$isClinic ? 'required' : 'nullable', 'array', 'min:1'],
            'photos.*' => ['image', 'max:5120'],

            'opt_location.name' => ['nullable', 'string', 'max:255'],
            'opt_location.address' => ['nullable', 'string'],
            'opt_location.city' => ['nullable', 'string', 'max:255'],
            'opt_location.state' => ['nullable', 'string', 'max:255'],
            'opt_location.postcode' => ['nullable', 'string', 'max:20'],
            'opt_location.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'opt_location.longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'no_domain' => ['nullable', 'boolean'],
            'domains' => ['nullable', 'array'],
            'domains.*.domain' => ['required_with:domains.*.role', 'string', 'max:255'],
            'domains.*.role' => ['required_with:domains.*.domain', 'string'],

            'admin_name' => ['required', 'string', 'min:2', 'max:255'],
            'admin_title' => ['required', 'string', 'min:2', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin_password' => ['required', 'confirmed', Password::defaults()],

            'confirm_authorized' => ['accepted'],
            'confirm_terms' => ['accepted'],
        ];
    }
}
