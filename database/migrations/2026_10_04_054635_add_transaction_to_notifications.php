<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transaksi yang dirujuk sebuah notifikasi.
 *
 * Dua belas dari tiga belas tempat notifikasi dibuat berbicara tentang sebuah
 * transaksi, tetapi hubungannya tidak pernah disimpan. Akibatnya notifikasi
 * hanya bisa tampil sebagai teks: isinya menyebut kode transaksi, sementara
 * orang yang membacanya harus mencarinya sendiri di daftar.
 *
 * Dibiarkan nullable, sebab ada notifikasi yang memang tidak menunjuk transaksi
 * mana pun, misalnya pemberitahuan perubahan kredensial akun. Notifikasi lama
 * pun tetap sah dengan kolom ini kosong.
 *
 * nullOnDelete dipilih alih-alih cascade: notifikasi adalah catatan bahwa
 * sesuatu pernah diberitahukan, dan itu tetap benar meski transaksinya kemudian
 * dihapus. Yang hilang hanya tautannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('transaction_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_id');
        });
    }
};
