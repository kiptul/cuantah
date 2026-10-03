<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bukti pembayaran berupa foto serah terima tunai atau tangkapan layar
     * transfer.
     *
     * Dibuat nullable karena transaksi yang sudah selesai sebelum kolom ini
     * ada tidak punya buktinya dan tidak boleh ikut dianggap cacat. Kewajiban
     * melampirkan bukti ditegakkan di sisi validasi, yaitu pada saat
     * pembayaran dicatat, bukan lewat batasan kolom.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('payment_proof_path')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('payment_proof_path');
        });
    }
};
