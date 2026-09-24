<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'actual_liter' => ['required', 'numeric', 'min:0.1', 'max:500'],
            'payment_method' => ['required', Rule::in(['cash', 'transfer'])],
            'payment_status' => ['required', Rule::in(['paid', 'unpaid'])],
            'notes' => ['nullable', 'string', 'max:700'],
        ];
    }
}
