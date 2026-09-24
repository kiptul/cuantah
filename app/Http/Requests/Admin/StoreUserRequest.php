<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:24'],
            'role' => ['required', Rule::in(['user', 'employee', 'admin'])],
            'password' => ['required', 'string', 'min:8'],
            'partner_ids' => ['nullable', 'array'],
            'partner_ids.*' => ['integer', Rule::exists('partners', 'id')],
        ];
    }
}
