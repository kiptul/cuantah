<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;

class DashboardService
{
    public function userSummary(User $user): array
    {
        $base = $user->transactions();

        return [
            'total_liter' => (clone $base)->where('status', Transaction::STATUS_COMPLETED)->sum('actual_liter'),
            'total_transactions' => (clone $base)->count(),
            'total_value' => (clone $base)->where('status', Transaction::STATUS_COMPLETED)->sum('total_value'),
            'latest_transactions' => (clone $base)->with('pickup.partner')->latest()->limit(5)->get(),
            'needs_action' => $this->needsAction($user),
            'notifications' => $user->notifications()->latest()->limit(5)->get(),
        ];
    }

    /**
     * Transaksi yang menunggu tindakan penyetor.
     *
     * Sebelumnya dasbor hanya melaporkan angka, sehingga konfirmasi pembayaran
     * dan tenggat sanggahan terlewat begitu saja tanpa pernah terlihat.
     *
     * @return \Illuminate\Support\Collection<int, array{transaction: Transaction, label: string, hint: string}>
     */
    private function needsAction(User $user)
    {
        return $user->transactions()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('payment_status', 'paid')
            ->where(function ($query) {
                $query->whereNull('payment_confirmed_at')
                    ->orWhere(fn ($q) => $q->whereNull('disputed_at')->where('updated_at', '>', now()->subDays(3)));
            })
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'transaction' => $transaction,
                'label' => $transaction->payment_confirmed_at === null
                    ? 'Konfirmasi pembayaran'
                    : 'Masih bisa disanggah',
                'hint' => $transaction->payment_confirmed_at === null
                    ? 'Mitra menandai sudah membayar. Benarkan bila uangnya sudah kamu terima.'
                    : 'Takaran tidak sesuai? Keberatan masih bisa diajukan.',
            ]);
    }
}
