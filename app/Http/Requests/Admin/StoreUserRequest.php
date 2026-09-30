<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

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
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'partner_ids' => ['nullable', 'array'],
            'partner_ids.*' => ['integer', Rule::exists('partners', 'id')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Seluruh data admin dan karyawan disaring lewat keterkaitan
                // mitra. Akun staff tanpa mitra akan masuk ke panel kosong.
                if (in_array($this->input('role'), ['admin', 'employee'], true) && blank($this->input('partner_ids'))) {
                    $validator->errors()->add('partner_ids', 'Pilih minimal satu mitra, sebab admin dan karyawan hanya melihat data mitranya.');
                }
            },
        ];
    }
}
