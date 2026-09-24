<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'partner_id' => ['required', 'exists:partners,id'],
            'volume_liter' => ['required', 'numeric', 'min:0.1', 'max:100000'],
            'destination' => ['required', 'string', 'max:180'],
            'distributed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:700'],
        ];
    }
}
