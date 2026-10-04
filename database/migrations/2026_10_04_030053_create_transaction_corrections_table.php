<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat koreksi volume transaksi.
 *
 * Karyawan menakar di lapangan dan mengetik angkanya di ponsel, jadi salah
 * ketik tidak terhindarkan. Sampai sekarang tidak ada jalan membetulkannya:
 * transaksi selesai terkunci, dan penyelesaian sanggahan hanya menulis
 * tanggapan tanpa menyentuh angkanya.
 *
 * Disimpan sebagai tabel, bukan kolom, karena satu transaksi bisa dikoreksi
 * lebih dari sekali dan tiap koreksi perlu menyebut keadaan sebelumnya.
 * Termasuk bukti bayar yang berlaku saat itu: begitu kekurangan dilunasi,
 * kolom bukti di transaksi menunjuk foto yang baru, dan tanpa baris ini
 * bukti pembayaran pertama kehilangan tautannya dari halaman.
 *
 * Nilai sebelum dan sesudah sama-sama disimpan. Menyimpan selisihnya saja
 * memaksa pembaca menyusun ulang riwayatnya dari belakang, dan satu baris
 * yang hilang membuat seluruh rantainya tidak terbaca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();

            /**
             * Admin yang mengoreksi. Dibiarkan kosong bila akunnya dihapus,
             * sebab menghapus riwayat koreksinya akan menghapus jejak uang.
             */
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('liter_before', 8, 2);
            $table->decimal('liter_after', 8, 2);
            $table->unsignedBigInteger('value_before');
            $table->unsignedBigInteger('value_after');

            /**
             * Status bayar sebelum koreksi, beserta bukti yang menyertainya.
             * Keduanya yang membuat baris ini bisa dibaca sebagai "dulu
             * begini, dibuktikan oleh foto ini".
             */
            $table->string('payment_status_before')->nullable();
            $table->string('payment_proof_path_before')->nullable();

            $table->text('reason');
            $table->timestamps();

            $table->index(['transaction_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_corrections');
    }
};
