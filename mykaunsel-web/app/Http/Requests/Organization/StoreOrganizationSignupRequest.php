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

    /**
     * HTML forms submit empty optional fields as "" rather than null, but
     * Laravel's `nullable` rule only skips other rules (regex, numeric,
     * between, ...) when the value is actually null — so an empty string
     * still fails them. Normalize empty strings to null here so `nullable`
     * behaves as intended everywhere below.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'ssm_number' => $this->filled('ssm_number') ? $this->input('ssm_number') : null,
            'location' => $this->normalizeEmptyStrings($this->input('location', [])),
            'opt_location' => $this->normalizeEmptyStrings($this->input('opt_location', [])),
            // The first domain row always exists in the DOM (even when
            // hidden behind "no domain"), so a blank leftover row shouldn't
            // be treated as an incomplete entry — only rows the user
            // actually typed a domain into count.
            'domains' => collect($this->input('domains', []))
                ->filter(fn ($row) => filled($row['domain'] ?? null))
                ->values()
                ->all(),
        ]);
    }

    private function normalizeEmptyStrings(array $data): array
    {
        return array_map(static fn ($value) => $value === '' ? null : $value, $data);
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
            // `org_domains.domain` has a DB-level unique constraint (a domain
            // can only belong to one org), and two rows in the same
            // submission can also repeat a domain — without checking both
            // here, a duplicate reaches the insert unvalidated and crashes
            // with a raw PDO exception instead of a normal form error.
            'domains.*.domain' => [
                'required_with:domains.*.role',
                'string',
                'max:255',
                'distinct:ignore_case',
                Rule::unique('org_domains', 'domain'),
            ],
            'domains.*.role' => ['required_with:domains.*.domain', 'string'],

            'admin_name' => ['required', 'string', 'min:2', 'max:255'],
            'admin_title' => ['required', 'string', 'min:2', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin_password' => ['required', 'confirmed', Password::defaults()],

            'confirm_authorized' => ['accepted'],
            'confirm_terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'domains.*.domain.unique' => 'This domain is already registered to another organization.',
            'domains.*.domain.distinct' => 'Each domain can only be listed once.',
        ];
    }
}
