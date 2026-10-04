<?php

namespace App\Http\Requests\Admin;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;

class CorrectTransactionRequest extends FormRequest
{
    /**
     * Batas mitra diperiksa di sini, bukan hanya di pengendali.
     *
     * Laravel menjalankan authorize() lebih dulu, baru rules(). Selama
     * pemeriksaan mitra tinggal di badan pengendali, permintaan dari admin
     * mitra lain yang kebetulan tidak lolos validasi akan dijawab 302 ke
     * belakang, bukan 403: jawabannya berubah menjadi "isianmu kurang"
     * padahal yang benar adalah "ini bukan milikmu".
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

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'actual_liter' => ['required', 'numeric', 'min:0.1', 'max:500'],
            /**
             * Alasan wajib, dan cukup panjang untuk menjelaskan sesuatu.
             * Koreksi mengubah nominal uang yang sudah dikabarkan ke
             * penyetor, jadi catatan "typo" saja tidak menolong siapa pun
             * yang membacanya bulan depan.
             */
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan koreksi wajib diisi.',
            'reason.min' => 'Alasan koreksi terlalu singkat untuk menjelaskan apa yang salah.',
        ];
    }
}
