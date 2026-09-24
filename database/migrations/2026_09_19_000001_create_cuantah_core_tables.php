<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

        Schema::create('oil_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('price_per_liter');
            $table->date('effective_date')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->index();
            $table->string('phone', 24);
            $table->text('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('capacity_liter')->default(0);
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('oil_price_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('estimated_liter', 8, 2);
            $table->decimal('actual_liter', 8, 2)->nullable();
            $table->unsignedInteger('price_per_liter');
            $table->unsignedInteger('estimated_total');
            $table->unsignedInteger('total_value')->nullable();
            $table->string('method')->index();
            $table->string('status')->default('pending')->index();
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['method', 'created_at']);
        });

        Schema::create('pickups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('address');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->date('pickup_date')->nullable()->index();
            $table->time('pickup_time')->nullable();
            $table->timestamp('scanned_at')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'status']);
            $table->index(['assigned_user_id', 'status']);
        });

        Schema::create('distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->decimal('volume_liter', 8, 2);
            $table->string('destination');
            $table->date('distributed_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('info')->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('distributions');
        Schema::dropIfExists('pickups');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('oil_prices');
        Schema::dropIfExists('user_addresses');
    }
};
