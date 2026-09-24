<?php

namespace App\Http\Controllers;

use App\Models\Transaction;

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
}
