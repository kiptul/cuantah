<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Pickup extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'partner_id',
        'assigned_user_id',
        'address',
        'latitude',
        'longitude',
        'pickup_date',
        'pickup_time',
        'scanned_at',
        'status',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'pickup_date' => 'date',
            'scanned_at' => 'datetime',
            'assigned_at' => 'datetime',
        ];
    }

    /**
     * Apakah tugas ini sudah lewat dari waktunya.
     *
     * Penjemputan dinilai dari tanggal yang dijanjikan kepada penyetor.
     * Drop-off tidak punya tanggal sama sekali, sehingga sebelumnya ia tidak
     * pernah menua: kartunya tetap berlencana abu-abu berhari-hari meski
     * jelantahnya sudah ada di mitra dan belum ditimbang.
     *
     * Drop-off dinilai dari sejak kapan ia menjadi tanggungan karyawan, yaitu
     * waktu barcode-nya discan, atau waktu penugasan bila admin menugaskannya
     * tanpa pemindaian.
     */
    public function isOverdue(): bool
    {
        if ($this->pickup_date !== null) {
            return $this->pickup_date->lt(today());
        }

        return $this->menungguSejak()?->lt(today()) ?? false;
    }

    /**
     * Lamanya tugas tanpa tanggal menunggu, dalam hari penuh.
     */
    public function daysWaiting(): ?int
    {
        $sejak = $this->menungguSejak();

        return $sejak === null ? null : (int) $sejak->diffInDays(today());
    }

    private function menungguSejak(): ?Carbon
    {
        return ($this->scanned_at ?? $this->assigned_at)?->copy()->startOfDay();
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Pickup yang sudah selesai atau ditolak tidak lagi bisa dipindah
     * karyawannya. Tanpa penjaga ini daftar admin menawarkan tombol
     * assign pada pickup yang perjalanannya sudah berakhir.
     */
    public function isAssignable(): bool
    {
        return ! in_array($this->status, ['completed', 'rejected'], true);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isStaff()) {
            return $query;
        }

        return $query->whereHas('transaction', fn (Builder $transaction) => $transaction->visibleTo($user));
    }
}
