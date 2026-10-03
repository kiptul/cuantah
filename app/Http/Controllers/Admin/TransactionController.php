<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VerifyTransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
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
            'transaction' => $transaction->load('user', 'partner', 'pickup.assignedUser'),
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
