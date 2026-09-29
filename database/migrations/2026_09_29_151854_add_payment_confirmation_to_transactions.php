<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sebelumnya status pembayaran hanya ditetapkan oleh karyawan atau admin,
     * tanpa satu pun jejak bahwa penyetor benar-benar menerima uangnya. Kolom
     * ini menutup lingkaran itu dari sisi penyetor.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('payment_confirmed_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('payment_confirmed_at');
        });
    }
};
