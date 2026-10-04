<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = ['user_id', 'transaction_id', 'title', 'message', 'type', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Alamat transaksi yang dirujuk, dari sudut pandang orang yang membacanya.
     *
     * Tabelnya satu dan dipakai ketiga peran, tetapi halaman transaksinya
     * berbeda-beda, jadi tujuannya dihitung dari peran pembacanya alih-alih
     * disimpan bersama notifikasinya. Menyimpan alamat jadi akan salah begitu
     * notifikasi yang sama dibaca peran lain.
     *
     * Mengembalikan null bila tidak ada transaksinya, atau bila pembacanya
     * sudah tidak bisa membukanya. Karyawan yang penugasannya dicabut masih
     * menyimpan notifikasi lamanya, dan menautkannya hanya akan mengantar dia
     * ke halaman 403.
     */
    public function urlUntuk(?User $pembaca): ?string
    {
        if ($pembaca === null || $this->transaction === null) {
            return null;
        }

        $transaksi = $this->transaction;

        if ($pembaca->isAdmin()) {
            return $pembaca->canAccessPartnerId($transaksi->partner_id)
                ? route('admin.transactions.show', $transaksi)
                : null;
        }

        if ($pembaca->isEmployee()) {
            return $transaksi->pickup?->assigned_user_id === $pembaca->id
                ? route('employee.transactions.show', $transaksi)
                : null;
        }

        return $transaksi->user_id === $pembaca->id
            ? route('transactions.show', $transaksi)
            : null;
    }
}
