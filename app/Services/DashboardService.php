<?php

namespace App\Services;

use App\Models\OilPrice;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * Banyaknya setoran yang bisa disanggah yang ditampilkan di dasbor.
     *
     * Sisanya tidak disembunyikan diam-diam melainkan disebut jumlahnya,
     * sebab hak menyanggah hangus dalam tiga hari dan penyetor tidak punya
     * cara lain mengetahui bahwa masih ada yang menunggu.
     */
    private const DISPUTABLE_SHOWN = 3;

    /**
     * Banyaknya setoran berjalan yang ditampilkan di dasbor.
     *
     * Daftarnya sebelumnya tanpa batas, sehingga penyetor dengan tiga puluh
     * setoran berjalan mendapat tiga puluh kartu sebelum sampai ke riwayat.
     */
    private const ACTIVE_SHOWN = 4;

    /**
     * Banyaknya tugas karyawan yang ditampilkan di dasbor.
     *
     * Lebih longgar daripada milik penyetor sebab karyawan memang wajar
     * memegang beberapa penjemputan sekaligus dalam satu hari.
     */
    private const TASKS_SHOWN = 6;

    /**
     * @return array<string, mixed>
     */
    public function userSummary(User $user): array
    {
        $completed = fn () => $user->transactions()->where('status', Transaction::STATUS_COMPLETED);

        /**
         * Hanya yang uangnya benar-benar sudah diserahkan mitra.
         *
         * Angka utama dasbor berlabel "CUAN diterima", sedangkan sebelumnya ia
         * menjumlah seluruh transaksi selesai termasuk yang belum dibayar.
         * Akibatnya kartu yang sama menyebut sejumlah uang sebagai diterima
         * dan, beberapa baris di bawahnya, menyebut sebagian darinya masih
         * akan dibayarkan.
         */
        $dibayar = fn () => $completed()->where('payment_status', 'paid');

        return [
            'total_liter' => (float) $completed()->sum('actual_liter'),
            'paid_value' => (int) $dibayar()->sum('total_value'),
            'paid_liter' => (float) $dibayar()->sum('actual_liter'),
            'month_liter' => (float) $completed()->where('completed_at', '>=', now()->startOfMonth())->sum('actual_liter'),
            /**
             * Yang belum dibayar mencakup payment_status kosong, bukan hanya
             * yang bertanda "unpaid". Tanpa itu, transaksi selesai yang
             * statusnya belum terisi hilang dari kedua angka sekaligus.
             */
            'unpaid_value' => (int) $completed()
                ->where(fn ($query) => $query->where('payment_status', '!=', 'paid')->orWhereNull('payment_status'))
                ->sum('total_value'),
            /**
             * Hanya yang selesai. Kartu ini berdiri bersebelahan dengan CUAN
             * diterima dan liter terkumpul, sehingga angkanya terbaca sebagai
             * pencapaian. Menghitung setoran yang ditolak dan dibatalkan di
             * dalamnya membuat penolakan tampak seperti keberhasilan.
             */
            'completed_transactions' => $completed()->count(),
            'current_price' => OilPrice::current(),
            'active_transactions' => $this->activeQuery($user)
                ->with('partner', 'pickup.assignedUser')
                ->latest()
                ->limit(self::ACTIVE_SHOWN)
                ->get(),
            'active_count' => $this->activeQuery($user)->count(),
            'latest_transactions' => $user->transactions()
                ->whereIn('status', Transaction::FINAL_STATUSES)
                ->latest()
                ->limit(5)
                ->get(),
            'disputable' => $this->disputable($user),
            'disputable_count' => $this->disputableQuery($user)->count(),
        ];
    }

    /**
     * Transaksi yang baru selesai dan takarannya masih bisa disanggah.
     *
     * Konfirmasi pembayaran oleh penyetor sudah dihapus: begitu karyawan
     * mengirim form penjemputan, transaksi selesai. Yang tersisa bagi
     * penyetor hanyalah hak menyanggah takaran dalam tiga hari.
     *
     * @return Collection<int, Transaction>
     */
    private function disputable(User $user): Collection
    {
        return $this->disputableQuery($user)
            ->latest('completed_at')
            ->limit(self::DISPUTABLE_SHOWN)
            ->get();
    }

    /**
     * Tugas karyawan yang belum berakhir, dipakai daftar sekaligus
     * perhitungannya.
     *
     * Jumlah dan daftarnya wajib berasal dari kueri yang sama. Angka besar
     * "Tugas terbuka" di dasbor sebelumnya menghitung koleksi yang
     * ditampilkan, sehingga begitu daftarnya dibatasi ia ikut berbohong.
     */
    private function taskQuery(User $employee): Builder
    {
        return Transaction::query()
            ->join('pickups', 'pickups.transaction_id', '=', 'transactions.id')
            ->where('pickups.assigned_user_id', $employee->id)
            ->whereNotIn('transactions.status', Transaction::FINAL_STATUSES);
    }

    /**
     * Setoran yang belum berakhir, dipakai daftar sekaligus perhitungannya.
     *
     * @return HasMany<Transaction, User>
     */
    private function activeQuery(User $user): HasMany
    {
        return $user->transactions()->whereNotIn('status', Transaction::FINAL_STATUSES);
    }

    /**
     * Dasar perhitungan sekaligus daftar, supaya jumlah yang disebut di layar
     * tidak pernah berasal dari syarat yang berbeda dengan isinya.
     *
     * @return HasMany<Transaction, User>
     */
    private function disputableQuery(User $user): HasMany
    {
        return $user->transactions()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereNull('disputed_at')
            ->where('completed_at', '>', now()->subDays(Transaction::DISPUTE_WINDOW_DAYS));
    }

    /**
     * @return array<string, mixed>
     */
    public function employeeSummary(User $employee): array
    {
        $completed = fn (): Builder => Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereHas('pickup', fn (Builder $pickup) => $pickup->where('assigned_user_id', $employee->id));

        return [
            /**
             * Tugas terbuka diurutkan menurut jadwal penyetor. Drop-off tidak
             * berjadwal sehingga diletakkan sesudahnya.
             */
            'tasks' => $this->taskQuery($employee)
                ->with('user', 'pickup', 'partner')
                ->select('transactions.*')
                ->orderByRaw('pickups.pickup_date is null')
                ->orderBy('pickups.pickup_date')
                ->orderBy('pickups.pickup_time')
                ->oldest('transactions.created_at')
                ->limit(self::TASKS_SHOWN)
                ->get(),
            'tasks_count' => $this->taskQuery($employee)->count(),
            'available_count' => Pickup::query()
                ->visibleTo($employee)
                ->whereNull('assigned_user_id')
                ->whereHas('transaction', fn (Builder $transaction) => $transaction
                    ->where('method', Transaction::METHOD_PICKUP)
                    ->whereNotIn('status', Transaction::FINAL_STATUSES))
                ->count(),
            'today_count' => $completed()->whereDate('completed_at', today())->count(),
            'today_liter' => (float) $completed()->whereDate('completed_at', today())->sum('actual_liter'),
            'month_count' => $completed()->where('completed_at', '>=', now()->startOfMonth())->count(),
            'month_liter' => (float) $completed()->where('completed_at', '>=', now()->startOfMonth())->sum('actual_liter'),
            'total_count' => $completed()->count(),
            'total_liter' => (float) $completed()->sum('actual_liter'),
            /**
             * Diurutkan menurut waktu selesai, bukan waktu sentuh terakhir.
             * Dengan updated_at, transaksi lama yang disunting admin melompat
             * ke puncak daftar "Baru saya selesaikan" meski tidak ada yang
             * baru diselesaikan.
             */
            'recent_completions' => $completed()
                ->with('user')
                ->latest('completed_at')
                ->limit(5)
                ->get(),
        ];
    }
}
