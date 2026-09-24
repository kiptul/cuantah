<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['partner_id', 'user_id']);
            $table->index(['user_id', 'partner_id']);
        });

        Schema::create('partner_delivery_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_distance_km', 8, 2);
            $table->decimal('max_distance_km', 8, 2)->nullable();
            $table->unsignedInteger('fee');
            $table->timestamps();

            $table->index(['partner_id', 'min_distance_km']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable()->after('oil_price_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('pickup_fee')->default(0)->after('estimated_total');

            $table->index(['partner_id', 'status']);
        });

        DB::table('transactions')
            ->join('pickups', 'pickups.transaction_id', '=', 'transactions.id')
            ->whereNotNull('pickups.partner_id')
            ->update(['transactions.partner_id' => DB::raw('pickups.partner_id')]);

        $partnerIds = DB::table('partners')->pluck('id');
        $staffIds = DB::table('users')->whereIn('role', ['admin', 'employee'])->pluck('id');
        $now = now();

        foreach ($staffIds as $userId) {
            foreach ($partnerIds as $partnerId) {
                DB::table('partner_user')->insertOrIgnore([
                    'partner_id' => $partnerId,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['partner_id', 'status']);
            $table->dropConstrainedForeignId('partner_id');
            $table->dropColumn('pickup_fee');
        });

        Schema::dropIfExists('partner_delivery_fees');
        Schema::dropIfExists('partner_user');
    }
};
