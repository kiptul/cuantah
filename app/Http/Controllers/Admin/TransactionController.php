<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyTransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionService;

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
        $this->authorize('manage', $transaction);

        return view('admin.transactions.show', [
            'transaction' => $transaction->load('user', 'partner', 'pickup.assignedUser'),
        ]);
    }

    public function verify(VerifyTransactionRequest $request, Transaction $transaction, TransactionService $service)
    {
        $service->verify($transaction, $request->validated());

        return back()->with('success', 'Transaksi selesai diverifikasi.');
    }

    public function markPickedUp(Transaction $transaction, TransactionService $service)
    {
        $this->authorize('manage', $transaction);
        $service->markPickedUp($transaction);

        return back()->with('success', 'Status transaksi diubah menjadi dijemput.');
    }

    public function markVerification(Transaction $transaction, TransactionService $service)
    {
        $this->authorize('manage', $transaction);
        $service->markVerification($transaction);

        return back()->with('success', 'Status transaksi diubah menjadi verifikasi.');
    }

    public function reject(Transaction $transaction, TransactionService $service)
    {
        $this->authorize('manage', $transaction);
        $service->reject($transaction);

        return back()->with('success', 'Transaksi ditolak.');
    }
}
