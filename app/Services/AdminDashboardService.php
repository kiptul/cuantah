<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $user = auth()->user();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth();

        $completed = fn (): Builder => Transaction::query()
            ->visibleTo($user)
            ->where('status', Transaction::STATUS_COMPLETED);

        $current = $this->periodTotals($completed(), $thisMonth, now());
        $previous = $this->periodTotals($completed(), $lastMonth, $thisMonth);

        return [
            'kpis' => [
                'liter' => ['current' => $current['liter'], 'previous' => $previous['liter']],
                'value' => ['current' => $current['value'], 'previous' => $previous['value']],
                'transactions' => ['current' => $current['count'], 'previous' => $previous['count']],
                'depositors' => ['current' => $current['depositors'], 'previous' => $previous['depositors']],
            ],
            'lifetime' => [
                'liter' => (float) $completed()->sum('actual_liter'),
                'value' => (int) $completed()->sum('total_value'),
                'transactions' => Transaction::visibleTo($user)->count(),
                // Dibatasi pada penyetor yang pernah bertransaksi dengan mitra yang
                // boleh diakses, bukan seluruh penyetor di sistem.
                'depositors' => User::where('role', 'user')
                    ->whereHas('transactions', fn ($transaction) => $transaction->visibleTo($user))
                    ->count(),
            ],
            'attention' => $this->attention($user),
            'open_disputes' => Transaction::query()
                ->visibleTo($user)
                ->with('user')
                ->whereNotNull('disputed_at')
                ->whereNull('dispute_resolved_at')
                ->oldest('disputed_at')
                ->limit(5)
                ->get(),
            'unpaid_transactions' => $completed()
                ->with('user')
                ->where('payment_status', 'unpaid')
                ->oldest('updated_at')
                ->limit(5)
                ->get(),
            'recent_completions' => $completed()
                ->with('user', 'pickup.assignedUser')
                ->latest('updated_at')
                ->limit(6)
                ->get(),
            'top_employees' => $this->topEmployees($user, $thisMonth),
            'partners' => Partner::query()
                ->accessibleTo($user)
                ->withAvailableLiter()
                ->orderBy('name')
                ->get(),
            'monthly' => $this->monthly($user),
        ];
    }

    /**
     * Jumlah hal yang menunggu tindakan admin.
     *
     * Dashboard sebelumnya hanya menghitung "pickup menunggu" dan "transaksi
     * selesai". Keberatan penyetor, utang pembayaran, dan jadwal yang
     * terlewat tidak pernah terlihat kecuali admin membuka transaksinya satu
     * per satu.
     *
     * @return array{unassigned: int, overdue: int, disputes: int, unpaid: int}
     */
    private function attention(User $user): array
    {
        $openPickups = fn (): Builder => Pickup::query()
            ->visibleTo($user)
            ->whereHas('transaction', fn (Builder $transaction) => $transaction
                ->where('method', Transaction::METHOD_PICKUP)
                ->whereNotIn('status', Transaction::FINAL_STATUSES));

        return [
            'unassigned' => $openPickups()->whereNull('assigned_user_id')->count(),
            'overdue' => $openPickups()->whereDate('pickup_date', '<', today())->count(),
            'disputes' => Transaction::visibleTo($user)
                ->whereNotNull('disputed_at')
                ->whereNull('dispute_resolved_at')
                ->count(),
            'unpaid' => Transaction::visibleTo($user)
                ->where('status', Transaction::STATUS_COMPLETED)
                ->where('payment_status', 'unpaid')
                ->count(),
        ];
    }

    /**
     * @return array{liter: float, value: int, count: int, depositors: int}
     */
    private function periodTotals(Builder $completed, Carbon $from, Carbon $until): array
    {
        $row = $completed
            ->where('completed_at', '>=', $from)
            ->where('completed_at', '<', $until)
            ->selectRaw('COALESCE(SUM(actual_liter), 0) as liter')
            ->selectRaw('COALESCE(SUM(total_value), 0) as value')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COUNT(DISTINCT user_id) as depositors')
            ->toBase()
            ->first();

        return [
            'liter' => (float) $row->liter,
            'value' => (int) $row->value,
            'count' => (int) $row->count,
            'depositors' => (int) $row->depositors,
        ];
    }

    /**
     * Karyawan dengan liter terbanyak bulan ini.
     *
     * @return Collection<int, object{name: string, completed_count: int, total_liter: float}>
     */
    private function topEmployees(User $user, Carbon $from): Collection
    {
        $partnerIds = $user->accessiblePartnerIds();

        if ($partnerIds === []) {
            return collect();
        }

        return Pickup::query()
            ->join('transactions', 'transactions.id', '=', 'pickups.transaction_id')
            ->join('users', 'users.id', '=', 'pickups.assigned_user_id')
            ->whereIn('transactions.partner_id', $partnerIds)
            ->where('transactions.status', Transaction::STATUS_COMPLETED)
            ->where('transactions.completed_at', '>=', $from)
            ->groupBy('users.id', 'users.name')
            ->select('users.id', 'users.name')
            ->selectRaw('COUNT(*) as completed_count')
            ->selectRaw('COALESCE(SUM(transactions.actual_liter), 0) as total_liter')
            ->orderByDesc('total_liter')
            ->limit(5)
            ->toBase()
            ->get();
    }

    /**
     * Volume dan nilai transaksi selesai selama 12 bulan, bulan kosong diisi nol.
     *
     * Berpatokan pada completed_at, bukan created_at. created_at adalah waktu
     * pengajuan, sehingga setoran yang diajukan akhir September dan ditimbang
     * awal Oktober terhitung September, padahal jelantahnya baru masuk pada
     * Oktober. Sisi penyetor sudah dipindahkan lebih dulu; dasbor admin
     * tertinggal, sehingga bulan yang sama bisa menunjukkan dua angka berbeda
     * tergantung siapa yang membukanya.
     *
     * Sebelumnya bulan tanpa transaksi hilang dari grafik sehingga jarak
     * antarbatang menyesatkan, dan transaksi batal ikut terhitung.
     *
     * @return array<int, array{month: string, label: string, volume: float, count: int, value: int}>
     */
    private function monthly(User $user): array
    {
        $monthExpression = $this->monthExpression();
        $start = now()->subMonths(11)->startOfMonth();

        $rows = Transaction::query()
            ->visibleTo($user)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('completed_at', '>=', $start)
            ->selectRaw("{$monthExpression} as month")
            ->selectRaw('COALESCE(SUM(actual_liter), 0) as volume')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(total_value), 0) as value')
            ->groupBy(DB::raw($monthExpression))
            ->toBase()
            ->get()
            ->keyBy('month');

        return collect(range(0, 11))
            ->map(function (int $offset) use ($start, $rows) {
                $month = $start->copy()->addMonths($offset);
                $row = $rows->get($month->format('Y-m'));

                return [
                    'month' => $month->format('Y-m'),
                    'label' => $month->translatedFormat('M y'),
                    'volume' => (float) ($row->volume ?? 0),
                    'count' => (int) ($row->count ?? 0),
                    'value' => (int) ($row->value ?? 0),
                ];
            })
            ->all();
    }

    /**
     * Ekspresi SQL untuk mengelompokkan transaksi per bulan (format: YYYY-MM).
     *
     * Setiap driver punya fungsi format tanggalnya sendiri, jadi dipilih
     * berdasarkan koneksi aktif agar query berjalan di MySQL maupun SQLite.
     */
    private function monthExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', completed_at)",
            'pgsql' => "to_char(completed_at, 'YYYY-MM')",
            default => 'DATE_FORMAT(completed_at, "%Y-%m")',
        };
    }
}
