<?php

namespace App\Http\Requests\Api;

use App\Support\Auth\LoginRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'email' => Str::lower(trim((string) $this->input('email', ''))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => LoginRules::name(),
            'email' => [...LoginRules::email(), Rule::unique('users', 'email')],
            'password' => LoginRules::password(confirmed: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return LoginRules::messages();
    }
}
