<?php

namespace App\Http\Requests\Admin;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyTransactionRequest extends FormRequest
{
    /**
     * Batas mitra diperiksa di sini, bukan hanya di pengendali.
     *
     * Laravel menjalankan authorize() lebih dulu, baru rules(). Selama
     * pemeriksaan mitra tinggal di badan pengendali, permintaan dari admin
     * mitra lain yang kebetulan tidak lolos validasi akan dijawab 302
     * ke belakang, bukan 403. Tindakannya memang tetap tidak terjadi, tetapi
     * jawabannya berubah menjadi "isianmu kurang" padahal yang benar adalah
     * "ini bukan milikmu".
     */
    public function authorize(): bool
    {
        $pengguna = $this->user();
        $transaksi = $this->route('transaction');

        if (! $pengguna?->isAdmin()) {
            return false;
        }

        return $transaksi instanceof Transaction
            && $pengguna->canAccessPartnerId($transaksi->partner_id);
    }

    public function rules(): array
    {
        return [
            'actual_liter' => ['required', 'numeric', 'min:0.1', 'max:500'],
            'payment_method' => ['required', Rule::in(['cash', 'transfer'])],
            'payment_status' => ['required', Rule::in(['paid', 'unpaid'])],
            /**
             * Bukti hanya diminta bila pembayaran benar-benar dicatat lunas.
             * Pada pembayaran yang ditunda belum ada uang yang berpindah,
             * jadi buktinya ditagih nanti saat transaksi ditandai lunas.
             */
            'payment_proof' => Transaction::paymentProofRules('required_if:payment_status,paid'),
            'notes' => ['nullable', 'string', 'max:700'],
        ];
    }
}
