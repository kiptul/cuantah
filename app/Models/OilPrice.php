<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OilPrice extends Model
{
    protected $fillable = ['price_per_liter', 'effective_date', 'is_active', 'notes'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public static function current(): ?self
    {
        return self::query()
            ->where('is_active', true)
            ->whereDate('effective_date', '<=', now())
            ->latest('effective_date')
            ->first();
    }
}
