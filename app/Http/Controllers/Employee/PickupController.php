<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyTransactionRequest;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;

class PickupController extends Controller
{
    public function available()
    {
        return view('employee.pickups.available', [
            'pickups' => Pickup::with('transaction.user', 'partner')
                ->visibleTo(auth()->user())
                ->whereNull('assigned_user_id')
                ->where(function ($query) {
                    $query->whereHas('transaction', fn ($transaction) => $transaction->where('method', Transaction::METHOD_PICKUP))
                        ->orWhereNotNull('scanned_at');
                })
                ->latest()
                ->paginate(10),
        ]);
    }

    public function claim(Pickup $pickup, TransactionService $service)
    {
        $this->authorize('claim', $pickup->transaction);

        $claimed = $service->claimPickup($pickup, auth()->user());

        return back()->with($claimed ? 'success' : 'error', $claimed
            ? 'Pickup berhasil kamu ambil.'
            : 'Pickup sudah diambil karyawan lain.');
    }

    public function scanForm()
    {
        return view('employee.pickups.scan');
    }

    public function scan(Request $request, TransactionService $service)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $pickup = $service->scanDropOff($data['code'], $request->user());

        if (! $pickup) {
            return back()->withErrors(['code' => 'Kode drop-off tidak ditemukan, bukan transaksi antar sendiri, atau sudah diambil karyawan lain.']);
        }

        return redirect()
            ->route('employee.transactions.show', $pickup->transaction)
            ->with('success', 'Barcode berhasil discan dan transaksi masuk ke daftar kamu.');
    }

    public function transactions()
    {
        return view('employee.transactions.index', [
            'transactions' => Transaction::with('user', 'pickup')
                ->with('partner')
                ->whereHas('pickup', fn ($pickup) => $pickup->where('assigned_user_id', auth()->id()))
                ->latest()
                ->paginate(12),
        ]);
    }

    public function show(Transaction $transaction)
    {
        $this->authorize('handle', $transaction);

        return view('employee.transactions.show', [
            'transaction' => $transaction->load('user', 'pickup.partner', 'pickup.assignedUser'),
        ]);
    }

    public function markPickedUp(Transaction $transaction, TransactionService $service)
    {
        $this->authorize('handle', $transaction);
        $service->markPickedUp($transaction);

        return back()->with('success', 'Status diubah menjadi dijemput.');
    }

    public function markVerification(Transaction $transaction, TransactionService $service)
    {
        $this->authorize('handle', $transaction);
        $service->markVerification($transaction);

        return back()->with('success', 'Status diubah menjadi verifikasi.');
    }

    public function verify(VerifyTransactionRequest $request, Transaction $transaction, TransactionService $service)
    {
        $service->verify($transaction, $request->validated());

        return redirect()
            ->route('employee.dashboard')
            ->with('success', 'Transaksi selesai diverifikasi.')
            ->with('transaction_completed', 'Transaksi selesai.');
    }
}
