<?php

namespace App\Providers;

use App\Models\Transaction;
use App\Policies\TransactionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Transaction::class, TransactionPolicy::class);

        /**
         * Satu kebijakan kata sandi untuk semua pintu masuk.
         *
         * Aturannya pernah ditulis ulang di lima berkas permintaan, dan yang
         * terjadi adalah pendaftaran mandiri serta reset kata sandi tertinggal
         * pada min:8 saja sementara tiga lainnya sudah menuntut huruf dan
         * angka. Orang bisa mendaftar dengan kata sandi yang kemudian ditolak
         * ketika ia menetapkannya sendiri dari pengaturan akun. Disimpan di
         * satu tempat supaya tidak bisa melenceng lagi.
         */
        Password::defaults(fn () => Password::min(8)->letters()->numbers());
    }
}
