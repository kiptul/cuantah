<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        return ($user->isStaff() && $user->canAccessPartnerId($transaction->partner_id))
            || $transaction->user_id === $user->id;
    }

    /**
     * Hanya penyetor yang boleh membenarkan bahwa uangnya sudah diterima.
     * Staff sengaja dikecualikan, sebab inti dari konfirmasi ini adalah
     * memberi suara kepada pihak yang selama ini hanya bisa menonton.
     */
    public function confirmPayment(User $user, Transaction $transaction): bool
    {
        return $transaction->user_id === $user->id
            && $transaction->payment_status === 'paid'
            && $transaction->payment_confirmed_at === null;
    }

    /**
     * Penyetor boleh membatalkan selama belum ada yang mengerjakannya.
     *
     * Sesudah status berpindah dari pending, karyawan sudah terkunci atau
     * sudah berangkat, sehingga pembatalan sepihak merugikan pihak lain.
     */
    public function cancel(User $user, Transaction $transaction): bool
    {
        return $transaction->user_id === $user->id
            && $transaction->status === Transaction::STATUS_PENDING;
    }

    /**
     * Penyetor boleh menyanggah takaran selama tiga hari sesudah transaksi
     * selesai. Batas waktu dipakai supaya sanggahan datang selagi jelantahnya
     * masih dapat ditelusuri, bukan berbulan kemudian.
     */
    public function dispute(User $user, Transaction $transaction): bool
    {
        return $transaction->user_id === $user->id
            && $transaction->status === Transaction::STATUS_COMPLETED
            && $transaction->disputed_at === null
            && $transaction->updated_at?->gt(now()->subDays(3));
    }

    /**
     * Hanya staff mitra terkait yang boleh menutup sanggahan.
     */
    public function resolveDispute(User $user, Transaction $transaction): bool
    {
        return $user->isStaff()
            && $user->canAccessPartnerId($transaction->partner_id)
            && $transaction->disputed_at !== null
            && $transaction->dispute_resolved_at === null;
    }
}
