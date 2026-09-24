<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:140'],
            'type' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:24'],
            'address' => ['required', 'string', 'max:700'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'capacity_liter' => ['required', 'integer', 'min:1', 'max:100000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'delivery_fees' => ['nullable', 'array', 'max:20'],
            'delivery_fees.*.min_distance_km' => ['required_with:delivery_fees', 'numeric', 'min:0', 'max:1000'],
            'delivery_fees.*.max_distance_km' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'delivery_fees.*.fee' => ['required_with:delivery_fees', 'integer', 'min:0', 'max:10000000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->input('delivery_fees', []) as $index => $fee) {
                    if (($fee['max_distance_km'] ?? null) === null || ($fee['max_distance_km'] ?? '') === '') {
                        continue;
                    }

                    if ((float) $fee['max_distance_km'] <= (float) ($fee['min_distance_km'] ?? 0)) {
                        $validator->errors()->add("delivery_fees.$index.max_distance_km", 'Jarak maksimal harus lebih besar dari jarak minimal.');
                    }
                }
            },
        ];
    }
}
