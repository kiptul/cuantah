<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penolakan sebelumnya hanya mengirim pesan generik, sehingga penyetor
     * tidak pernah tahu apa yang salah dan tidak belajar apa pun untuk
     * setoran berikutnya.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('rejection_reason', 500)->nullable()->after('payment_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
