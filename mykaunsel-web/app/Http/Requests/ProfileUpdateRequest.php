<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /** True when the submitted email differs from the one on the account. */
    public function changesEmail(): bool
    {
        return strtolower((string) $this->input('email')) !== strtolower((string) $this->user()->email);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // Changing the email address is a sensitive action: prove it is really the owner.
            'current_password' => Rule::when($this->changesEmail(), ['required', 'current_password'], ['nullable']),
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Enter your current password to change your email address.',
            'current_password.current_password' => 'That password is not correct.',
        ];
    }
}
