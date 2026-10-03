<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * Menyajikan bukti pembayaran dari disk privat.
     *
     * Berkasnya tidak pernah berada di bawah public/, jadi satu-satunya jalan
     * membukanya adalah lewat sini, dan di sini otorisasinya diperiksa.
     */
    public function paymentProof(Transaction $transaction): StreamedResponse
    {
        $this->authorize('view', $transaction);

        abort_if($transaction->payment_proof_path === null, 404);
        abort_unless(Storage::disk('local')->exists($transaction->payment_proof_path), 404);

        return Storage::disk('local')->response($transaction->payment_proof_path);
    }

    public function cancel(Transaction $transaction, TransactionService $service): RedirectResponse
    {
        $this->authorize('cancel', $transaction);

        $service->cancel($transaction);

        return redirect()->route('transactions.index')->with('success', 'Setoran dibatalkan.');
    }

    /**
     * Penyetor menyanggah takaran volume.
     *
     * Verifikasi sebelumnya sepenuhnya satu arah: angka karyawan langsung
     * menjadi dasar bayaran tanpa ada cara membantahnya.
     */
    public function dispute(Request $request, Transaction $transaction, TransactionService $service): RedirectResponse
    {
        $this->authorize('dispute', $transaction);

        $data = $request->validate([
            'dispute_reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'dispute_reason.required' => 'Jelaskan keberatanmu agar mitra bisa menindaklanjuti.',
            'dispute_reason.min' => 'Keterangan terlalu singkat untuk bisa ditindaklanjuti.',
        ]);

        $service->dispute($transaction, $data['dispute_reason']);

        return back()->with('success', 'Keberatanmu sudah dikirim ke mitra.');
    }
}
