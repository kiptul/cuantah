<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OilPrice extends Model
{
    use HasFactory;

    protected $fillable = ['price_per_liter', 'effective_date', 'is_active', 'notes'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Harga yang sedang berlaku.
     *
     * Urutan kedua berdasarkan id diperlukan: bila admin mengoreksi harga
     * pada tanggal yang sama, tanpa penentu ini baris mana yang terpilih
     * bergantung pada urutan bawaan basis data, sehingga koreksi bisa
     * diabaikan tanpa pesan apa pun.
     */
    public static function current(): ?self
    {
        return self::query()
            ->where('is_active', true)
            ->whereDate('effective_date', '<=', now())
            ->latest('effective_date')
            ->latest('id')
            ->first();
    }
}
