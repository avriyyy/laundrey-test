<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('prefix')) {
            $this->merge(['prefix' => strtoupper((string) $this->input('prefix'))]);
        }
    }

    public function rules(): array
    {
        return [
            'laundry_name' => ['required', 'string', 'max:100'],
            'prefix' => ['required', 'string', 'size:3', 'regex:/^[A-Z]+$/', 'unique:tenants,prefix'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'prefix.regex' => 'Prefix must be 3 capital letters',
            'prefix.unique' => 'Prefix already taken by another laundry',
        ];
    }
}
