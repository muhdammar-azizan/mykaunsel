<?php

namespace App\Http\Requests\Counselor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreCounselorSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'kb_number' => ['required', 'string', 'regex:/^KB\d{5}$/i'],
            'pa_number' => ['required', 'string', 'regex:/^PA\d{5}$/i'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'confirm_accurate' => ['accepted'],
            'confirm_terms' => ['accepted'],
        ];
    }
}
