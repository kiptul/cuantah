<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PickupController extends Controller
{
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
            ->with('success', 'QR berhasil dipindai dan transaksi masuk ke daftar kamu.');
    }

    public function transactions()
    {
        return view('employee.transactions.index', [
            'transactions' => Transaction::with('user', 'pickup')
                ->with('partner')
                ->whereHas('pickup', fn ($pickup) => $pickup->where('assigned_user_id', Auth::id()))
                ->latest()
                ->paginate(12),
        ]);
    }

    public function show(Transaction $transaction)
    {
        $this->ensureAssigned($transaction);

        return view('employee.transactions.show', [
            'transaction' => $transaction->load('user', 'pickup.partner', 'pickup.assignedUser'),
        ]);
    }

    public function verify(Request $request, Transaction $transaction, TransactionService $service)
    {
        $this->ensureAssigned($transaction);

        $data = $request->validate([
            'actual_liter' => ['required', 'numeric', 'min:0.1', 'max:500'],
            'payment_method' => ['required', 'in:cash,transfer'],
            'payment_status' => ['required', 'in:paid,unpaid'],
            /**
             * Bukti hanya diminta bila pembayaran dicatat lunas. Karyawan
             * yang mencatat pembayaran ditunda belum memegang uang apa pun,
             * jadi buktinya ditagih di sisi admin saat ditandai lunas.
             */
            'payment_proof' => Transaction::paymentProofRules('required_if:payment_status,paid'),
            'notes' => ['nullable', 'string', 'max:700'],
        ]);

        $data['payment_proof'] = $request->file('payment_proof');

        $transaction = $service->verify($transaction, $data);

        return redirect()
            ->route('employee.dashboard')
            ->with('success', sprintf(
                'Transaksi %s selesai: %s L, Rp%s.',
                $transaction->code,
                number_format((float) $transaction->actual_liter, 2, ',', '.'),
                number_format((int) $transaction->total_value, 0, ',', '.'),
            ));
    }

    /**
     * Menolak setoran yang tidak memenuhi kriteria, langsung dari lokasi.
     *
     * Sebelumnya karyawan yang tiba di tempat dan menemukan jelantah
     * bercampur air tidak punya jalan keluar: menyelesaikan transaksi berarti
     * membayar barang yang ditolak, membiarkannya berarti tugas menggantung,
     * dan penyetor pun sudah tidak bisa membatalkan sebab statusnya bukan
     * lagi menunggu.
     */
    public function reject(Request $request, Transaction $transaction, TransactionService $service)
    {
        $this->ensureAssigned($transaction);

        /**
         * Alasan diwajibkan, sama seperti penolakan oleh admin. Tanpa itu
         * penyetor hanya tahu setorannya gagal dan tidak tahu apa yang perlu
         * diperbaiki pada setoran berikutnya.
         */
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'rejection_reason.required' => 'Sebutkan alasan penolakan agar penyetor tahu apa yang perlu diperbaiki.',
            'rejection_reason.min' => 'Alasan terlalu singkat untuk bisa dipahami penyetor.',
        ]);

        $service->reject($transaction, $data['rejection_reason'], $request->user());

        return redirect()
            ->route('employee.dashboard')
            ->with('success', 'Setoran '.$transaction->code.' ditolak. Penyetor sudah diberi tahu alasannya.');
    }

    /**
     * Rute karyawan juga terbuka bagi admin, tetapi admin tetap terikat pada
     * mitranya sendiri. Tanpa pengikatan itu, admin mitra A bisa membuka dan
     * memverifikasi transaksi mitra B lewat jalur ini, padahal rute admin
     * sendiri sudah menutupnya.
     */
    private function ensureAssigned(Transaction $transaction): void
    {
        $user = Auth::user();

        $ditugaskan = $transaction->pickup?->assigned_user_id === $user->id;
        $adminMitraIni = $user->isAdmin() && $user->canAccessPartnerId($transaction->partner_id);

        abort_unless($ditugaskan || $adminMitraIni, 403);
    }
}
