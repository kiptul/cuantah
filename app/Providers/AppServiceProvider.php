<?php

namespace App\Providers;

use App\Models\Transaction;
use App\Policies\TransactionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Transaction::class, TransactionPolicy::class);

        /*
         * Semua pintu masuk memakai aturan kata sandi yang sama. Dulu aturan
         * ini tersebar di beberapa request, lalu pendaftaran dan reset password
         * tertinggal di min:8 saat form lain sudah mewajibkan huruf dan angka.
         */
        Password::defaults(fn () => Password::min(8)->letters()->numbers());
    }
}
