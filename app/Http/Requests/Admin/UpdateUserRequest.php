<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'phone' => ['nullable', 'string', 'max:24'],
            'role' => ['required', Rule::in(['user', 'employee', 'admin'])],
            'partner_ids' => ['nullable', 'array'],
            'partner_ids.*' => ['integer', Rule::exists('partners', 'id')],
        ];
    }
}
