<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu koreksi volume pada sebuah transaksi.
 *
 * Menyimpan keadaan sebelum dan sesudah, bukan selisihnya, supaya tiap baris
 * bisa dibaca sendiri tanpa menyusun ulang rantainya dari belakang.
 */
class TransactionCorrection extends Model
{
    protected $fillable = [
        'transaction_id',
        'corrected_by',
        'liter_before',
        'liter_after',
        'value_before',
        'value_after',
        'payment_status_before',
        'payment_proof_path_before',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'liter_before' => 'decimal:2',
            'liter_after' => 'decimal:2',
            'value_before' => 'integer',
            'value_after' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    /**
     * Selisih nilai yang ditimbulkan koreksi ini.
     *
     * Positif berarti penyetor kurang dibayar sebelumnya, negatif berarti
     * kelebihan.
     */
    public function valueDifference(): int
    {
        return $this->value_after - $this->value_before;
    }

    public function hasProofBefore(): bool
    {
        return $this->payment_proof_path_before !== null;
    }
}
