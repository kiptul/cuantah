<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

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
            'notifications' => $this->notificationsAndMarkRead($user),
        ];
    }

    /**
     * Lima notifikasi terbaru, sekaligus menandai yang belum terbaca.
     *
     * Kolom read_at sebelumnya ada tetapi tidak pernah diisi, sehingga
     * notifikasi menumpuk tanpa pernah bisa dibedakan mana yang sudah dilihat.
     * Penandaan dilakukan setelah data diambil agar lencana "baru" masih
     * sempat tampil pada kunjungan yang memunculkannya.
     *
     * @return Collection<int, Notification>
     */
    private function notificationsAndMarkRead(User $user)
    {
        $notifications = $user->notifications()->latest()->limit(5)->get();

        $belumTerbaca = $notifications->whereNull('read_at')->pluck('id');

        if ($belumTerbaca->isNotEmpty()) {
            Notification::whereIn('id', $belumTerbaca)->update(['read_at' => now()]);
        }

        return $notifications;
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
