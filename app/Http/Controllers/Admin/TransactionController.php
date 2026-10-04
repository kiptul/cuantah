<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CorrectTransactionRequest;
use App\Http\Requests\Admin\VerifyTransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function scanForm()
    {
        return view('admin.transactions.scan');
    }

    /**
     * Membuka setoran antar sendiri dari kode QR-nya.
     *
     * Cadangan untuk saat seluruh karyawan sedang menjemput dan penyetor
     * terlanjur datang ke lokasi mitra. Pemindaiannya tidak mengubah apa pun,
     * hanya mengantar ke halaman rinciannya; yang mencatat penerimaannya
     * adalah panel Selesaikan di sana.
     */
    public function scan(Request $request, TransactionService $service)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);

        $transaction = $service->findByCodeFor($data['code'], $request->user());

        /**
         * Sebabnya disebut satu per satu. "Kode tidak ditemukan" untuk
         * transaksi jemput akan membuat admin memindai ulang QR yang
         * sebenarnya sudah terbaca benar, lalu menyimpulkan pemindainya rusak.
         */
        if (! $transaction) {
            return back()->withInput()->withErrors(['code' => 'Kode tidak ditemukan, atau transaksinya bukan milik mitra yang kamu kelola.']);
        }

        if ($transaction->isFinal()) {
            return back()->withInput()->withErrors(['code' => 'Transaksi '.$transaction->code.' sudah '.$transaction->statusLabel().', jadi tidak ada lagi yang perlu diterima.']);
        }

        if (! $transaction->adminCanComplete()) {
            return back()->withInput()->withErrors(['code' => 'Transaksi '.$transaction->code.' memakai metode jemput, dan diselesaikan karyawan di lapangan. Tugaskan karyawan lewat halaman Pickup.']);
        }

        return redirect()
            ->route('admin.transactions.show', $transaction)
            ->with('success', 'QR terbaca: '.$transaction->code.'. Takar jelantahnya, lalu selesaikan di bawah.');
    }

    public function index(Request $request)
    {
        $filters = [
            'q' => is_string($q = $request->query('q')) ? trim($q) : '',
            'status' => $this->onlyAllowed($request->query('status'), Transaction::statuses()),
            'method' => $this->onlyAllowed($request->query('method'), Transaction::methods()),
        ];

        $transactions = Transaction::with('user', 'partner', 'pickup.assignedUser')
            ->visibleTo($request->user())
            ->search($filters['q'])
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['method'], fn ($query, string $method) => $query->where('method', $method))
            ->latest()
            ->paginate(12)
            /**
             * Tanpa withQueryString, tautan halaman 2 kehilangan filternya dan
             * admin dikembalikan ke seluruh transaksi tanpa pemberitahuan.
             */
            ->withQueryString();

        return view('admin.transactions.index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'statusLabels' => Transaction::STATUS_LABELS,
            'methodLabels' => [
                Transaction::METHOD_PICKUP => 'Jemput',
                Transaction::METHOD_DROP_OFF => 'Antar',
            ],
        ]);
    }

    /**
     * Meneruskan nilai query string hanya bila termasuk daftar yang sah.
     *
     * Nilai asing diabaikan alih-alih ditolak dengan error. Penyaring ini
     * dikendalikan select, jadi nilai aneh hanya datang dari URL yang diubah
     * tangan, dan halaman yang tetap terbuka lebih berguna daripada redirect
     * yang berisiko berputar kembali ke URL yang sama.
     *
     * @param  array<int, string>  $allowed
     */
    private function onlyAllowed(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }

    public function show(Transaction $transaction)
    {
        $this->ensureVisible($transaction);

        return view('admin.transactions.show', [
            'transaction' => $transaction->load('user', 'partner', 'pickup.assignedUser', 'corrections.correctedBy'),
        ]);
    }

    public function verify(VerifyTransactionRequest $request, Transaction $transaction, TransactionService $service)
    {
        $this->ensureVisible($transaction);
        $service->verify($transaction, [
            ...$request->validated(),
            'payment_proof' => $request->file('payment_proof'),
        ]);

        return back()->with('success', 'Transaksi selesai diverifikasi.');
    }

    /**
     * Mengoreksi volume yang salah diketik karyawan.
     *
     * Transaksi selesai terkunci oleh pastikanBelumFinal, dan penyelesaian
     * sanggahan hanya menulis tanggapan tanpa menyentuh angkanya. Tanpa
     * pintu ini, satu-satunya "perbaikan" yang tersedia adalah permintaan
     * maaf tertulis.
     */
    public function correct(CorrectTransactionRequest $request, Transaction $transaction, TransactionService $service)
    {
        $transaction = $service->correctVolume(
            $transaction,
            (float) $request->validated('actual_liter'),
            $request->validated('reason'),
            $request->user(),
        );

        return back()->with('success', sprintf(
            'Volume %s dikoreksi menjadi %s L, nilainya Rp%s.',
            $transaction->code,
            number_format((float) $transaction->actual_liter, 2, ',', '.'),
            number_format((int) $transaction->total_value, 0, ',', '.'),
        ));
    }

    /**
     * Melunasi transaksi selesai yang sebelumnya dicatat belum dibayar.
     */
    public function markPaid(Request $request, Transaction $transaction, TransactionService $service)
    {
        $this->ensureVisible($transaction);

        $request->validate([
            'payment_proof' => Transaction::paymentProofRules('required'),
        ]);

        $service->markPaid($transaction, $request->file('payment_proof'));

        return back()->with('success', 'Transaksi '.$transaction->code.' ditandai lunas.');
    }

    public function reject(Request $request, Transaction $transaction, TransactionService $service)
    {
        $this->ensureVisible($transaction);

        // Alasan diwajibkan. Penolakan tanpa keterangan membuat penyetor
        // tidak tahu apa yang perlu diperbaiki pada setoran berikutnya.
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'rejection_reason.required' => 'Sebutkan alasan penolakan agar penyetor tahu apa yang perlu diperbaiki.',
            'rejection_reason.min' => 'Alasan terlalu singkat untuk bisa dipahami penyetor.',
        ]);

        $service->reject($transaction, $data['rejection_reason']);

        return back()->with('success', 'Transaksi ditolak beserta alasannya.');
    }

    private function ensureVisible(Transaction $transaction): void
    {
        abort_unless(Auth::user()->canAccessPartnerId($transaction->partner_id), 403);
    }

    public function resolveDispute(Request $request, Transaction $transaction, TransactionService $service)
    {
        $this->authorize('resolveDispute', $transaction);

        $data = $request->validate([
            'dispute_resolution' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $service->resolveDispute($transaction, $data['dispute_resolution']);

        return back()->with('success', 'Tanggapan atas keberatan sudah dikirim ke penyetor.');
    }
}
