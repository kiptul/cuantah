<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (! Schema::hasColumn('partners', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('address');
            }

            if (! Schema::hasColumn('partners', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });

        Schema::table('pickups', function (Blueprint $table) {
            if (! Schema::hasColumn('pickups', 'assigned_user_id')) {
                $table->foreignId('assigned_user_id')->nullable()->after('partner_id')->constrained('users')->nullOnDelete();
                $table->index(['assigned_user_id', 'status']);
            }

            if (! Schema::hasColumn('pickups', 'scanned_at')) {
                $table->timestamp('scanned_at')->nullable()->after('pickup_time')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pickups', function (Blueprint $table) {
            if (Schema::hasColumn('pickups', 'assigned_user_id')) {
                $table->dropConstrainedForeignId('assigned_user_id');
            }

            if (Schema::hasColumn('pickups', 'scanned_at')) {
                $table->dropColumn('scanned_at');
            }
        });

        Schema::table('partners', function (Blueprint $table) {
            if (Schema::hasColumn('partners', 'longitude')) {
                $table->dropColumn('longitude');
            }

            if (Schema::hasColumn('partners', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};
