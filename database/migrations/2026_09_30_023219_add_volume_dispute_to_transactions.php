<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Takaran karyawan sebelumnya tidak bisa disanggah sama sekali. Penyetor
     * hanya menerima angka akhir tanpa jalur keberatan, padahal justru volume
     * itulah yang menentukan bayarannya.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('disputed_at')->nullable()->after('rejection_reason');
            $table->string('dispute_reason', 500)->nullable()->after('disputed_at');
            $table->timestamp('dispute_resolved_at')->nullable()->after('dispute_reason');
            $table->string('dispute_resolution', 500)->nullable()->after('dispute_resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['disputed_at', 'dispute_reason', 'dispute_resolved_at', 'dispute_resolution']);
        });
    }
};
