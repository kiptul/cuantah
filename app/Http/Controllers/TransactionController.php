<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = auth()->user()
            ->transactions()
            ->with('partner', 'pickup.assignedUser')
            ->latest()
            ->paginate(10);

        return view('user.transactions.index', compact('transactions'));
    }

    public function show(Transaction $transaction)
    {
        $this->authorize('view', $transaction);

        return view('user.transactions.show', [
            'transaction' => $transaction->load('partner', 'pickup.assignedUser'),
        ]);
    }

    /**
     * Penyetor membenarkan bahwa uangnya sudah benar-benar diterima.
     *
     * Tanpa langkah ini status lunas sepenuhnya bergantung pada pengakuan
     * karyawan, sehingga tidak ada yang membedakan pembayaran yang sungguh
     * terjadi dari yang hanya ditandai.
     */
    public function confirmPayment(Transaction $transaction): RedirectResponse
    {
        $this->authorize('confirmPayment', $transaction);

        $transaction->update(['payment_confirmed_at' => now()]);

        Notification::create([
            'user_id' => $transaction->user_id,
            'title' => 'Pembayaran dikonfirmasi',
            'message' => 'Kamu menyatakan sudah menerima pembayaran untuk transaksi '.$transaction->code.'.',
            'type' => 'transaction',
        ]);

        return back()->with('success', 'Terima kasih, konfirmasi penerimaan pembayaran sudah dicatat.');
    }

    public function cancel(Transaction $transaction, TransactionService $service): RedirectResponse
    {
        $this->authorize('cancel', $transaction);

        $service->cancel($transaction);

        return redirect()->route('transactions.index')->with('success', 'Setoran dibatalkan.');
    }
}
