<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'password' => ['nullable', 'string', 'min:8'],
            'partner_ids' => ['nullable', 'array'],
            'partner_ids.*' => ['integer', Rule::exists('partners', 'id')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $target = $this->route('user');

                // Admin tidak boleh menurunkan perannya sendiri. Bila admin
                // terakhir melakukannya, tidak ada lagi yang bisa masuk ke
                // panel untuk mengembalikannya.
                if ($target instanceof User && $this->user()->is($target) && $this->input('role') !== 'admin') {
                    $validator->errors()->add('role', 'Kamu tidak bisa mengubah peranmu sendiri. Minta admin lain yang melakukannya.');
                }

                // Staff tanpa mitra tidak melihat apa pun: seluruh scope data
                // bertumpu pada keterkaitan itu. Lebih baik ditolak di sini
                // daripada menghasilkan akun yang diam-diam kosong.
                if (in_array($this->input('role'), ['admin', 'employee'], true) && blank($this->input('partner_ids'))) {
                    $validator->errors()->add('partner_ids', 'Pilih minimal satu mitra, sebab admin dan karyawan hanya melihat data mitranya.');
                }
            },
        ];
    }
}
