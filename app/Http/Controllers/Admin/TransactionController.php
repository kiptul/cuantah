<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VerifyTransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Transaction::with('user', 'partner', 'pickup.assignedUser')
            ->visibleTo(auth()->user())
            ->latest()
            ->paginate(12);

        return view('admin.transactions.index', compact('transactions'));
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
        $service->verify($transaction, $request->validated());

        return back()->with('success', 'Transaksi selesai diverifikasi.');
    }

    public function markPickedUp(Transaction $transaction, TransactionService $service)
    {
        $this->ensureVisible($transaction);
        $service->markPickedUp($transaction);

        return back()->with('success', 'Status transaksi diubah menjadi dijemput.');
    }

    public function markVerification(Transaction $transaction, TransactionService $service)
    {
        $this->ensureVisible($transaction);
        $service->markVerification($transaction);

        return back()->with('success', 'Status transaksi diubah menjadi verifikasi.');
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
        abort_unless(auth()->user()->canAccessPartnerId($transaction->partner_id), 403);
    }
}
