<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Waktu transaksi dinyatakan selesai.
 *
 * Tiga dasbor menghitung volume selesai per periode dengan tiga patokan yang
 * berbeda: penyetor memakai created_at, karyawan memakai updated_at, dan admin
 * memakai created_at. Tidak satu pun menjawab pertanyaan yang sebenarnya
 * diajukan, yaitu kapan jelantahnya ditimbang dan dibukukan.
 *
 * created_at adalah waktu pengajuan, sehingga setoran yang diajukan akhir
 * Januari dan ditimbang awal Februari terhitung Januari. updated_at adalah
 * waktu tulis terakhir, sehingga penyelesaian sanggahan di bulan Maret
 * memindahkan setoran Januari ke Maret.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('paid_at');
        });

        /**
         * Baris lama diisi dari updated_at. Itu tebakan terbaik yang tersedia:
         * bagi transaksi selesai yang tidak disentuh lagi sesudahnya, nilainya
         * tepat. Membiarkannya kosong akan membuat seluruh riwayat menghilang
         * dari hitungan bulanan.
         */
        DB::table('transactions')
            ->where('status', 'completed')
            ->whereNull('completed_at')
            ->update(['completed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
