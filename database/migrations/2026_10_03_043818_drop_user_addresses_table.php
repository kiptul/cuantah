<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membuang tabel alamat tersimpan yang tidak pernah tersambung.
 *
 * Tabelnya dibuat sejak migrasi inti bersama model UserAddress dan relasi
 * User::addresses(), lalu tidak satu pun controller, tampilan, seeder, atau
 * test pernah menyentuhnya. Tidak ada jalur kode yang menulis ke sana, jadi
 * tabel ini kosong di setiap pemasangan, termasuk di server.
 *
 * Kebutuhannya sendiri sudah terjawab dengan cara lain: halaman setoran
 * mengingat alamat jemput terakhir dengan membaca pickup terakhir penyetor,
 * sehingga rancangan alamat bernama ini tidak lagi diperlukan.
 *
 * down() membangun ulang tabelnya persis seperti semula, sehingga perubahan ini
 * bisa dibalik. Yang tidak bisa dikembalikan hanya isinya, dan isinya tidak
 * pernah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_addresses');
    }

    public function down(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->default('Utama');
            $table->text('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'label']);
        });
    }
};
