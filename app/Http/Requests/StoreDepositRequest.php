<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'partner_id' => ['required', Rule::exists('partners', 'id')->where('status', 'active')],
            'method' => ['required', Rule::in([Transaction::METHOD_DROP_OFF, Transaction::METHOD_PICKUP])],
            'estimated_liter' => ['required', 'numeric', 'min:0.5', 'max:500'],
            'address' => ['required', 'string', 'max:700'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'pickup_date' => ['required_if:method,pickup', 'nullable', 'date', 'after_or_equal:today'],
            'pickup_time' => ['required_if:method,pickup', 'nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:700'],
        ];
    }
}
