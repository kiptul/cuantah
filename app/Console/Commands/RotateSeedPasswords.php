<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as AturanPassword;

#[Signature('cuantah:rotate-seed-passwords {--email=* : Batasi rotasi pada alamat email tertentu} {--force : Rotasi juga akun yang passwordnya sudah bukan bawaan} {--password= : Pakai kata sandi ini alih-alih membuat yang acak}')]
#[Description('Mengganti password akun seed, acak secara bawaan atau sesuai --password')]
class RotateSeedPasswords extends Command
{
    /**
     * Akun demo yang dibuat DatabaseSeeder dan masih berpassword bawaan.
     */
    private const SEED_EMAILS = [
        'admin@cuantah.test',
        'karyawan@cuantah.test',
        'user@cuantah.test',
    ];

    /**
     * Kata sandi bawaan yang dipakai seeder versi lama.
     *
     * Seeder sekarang mengambil nilainya dari SEED_PASSWORD atau membuat
     * acak, sehingga pemeriksaan ini hanya mengenai basis data yang sudah
     * terlanjur disemai sebelum perubahan itu — termasuk yang sudah
     * berjalan di server. Untuk kasus lain, pakai --force.
     */
    private const DEFAULT_PASSWORD = 'password';

    private const PASSWORD_LENGTH = 20;

    public function handle(): int
    {
        $emails = $this->option('email') ?: self::SEED_EMAILS;
        $force = (bool) $this->option('force');

        /**
         * Kata sandi pilihan sendiri, bila diberikan.
         *
         * Diperiksa terhadap kebijakan yang sama dengan yang berlaku di
         * aplikasi. Tanpa pemeriksaan ini, sebuah akun bisa berakhir memakai
         * kata sandi yang justru ditolak ketika pemiliknya hendak
         * menetapkannya sendiri lewat Pengaturan akun, dan itu sudah pernah
         * terjadi dengan SEED_PASSWORD yang hanya berisi angka.
         */
        $pilihan = $this->option('password');

        if ($pilihan !== null) {
            $periksa = Validator::make(
                ['password' => $pilihan],
                ['password' => ['required', AturanPassword::defaults()]],
            );

            if ($periksa->fails()) {
                $this->components->error($periksa->errors()->first('password'));

                return self::FAILURE;
            }
        }

        $users = User::query()->whereIn('email', $emails)->get()->keyBy('email');

        foreach (array_diff($emails, $users->keys()->all()) as $missingEmail) {
            $this->components->warn("Akun {$missingEmail} tidak ada, dilewati.");
        }

        if ($users->isEmpty()) {
            $this->components->error('Tidak ada akun yang cocok. Tidak ada yang dirotasi.');

            return self::FAILURE;
        }

        $rotated = [];
        $skipped = [];

        foreach ($users as $user) {
            if (! $force && ! Hash::check(self::DEFAULT_PASSWORD, $user->password)) {
                $skipped[] = $user->email;

                continue;
            }

            $password = $pilihan ?? Str::password(self::PASSWORD_LENGTH, symbols: false);

            /**
             * Remember token ikut diputar: tanpa itu cookie "ingat saya" yang
             * dibuat dengan password bawaan tetap sah, jadi rotasi password
             * saja belum mengeluarkan siapa pun yang sudah masuk.
             */
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            $rotated[] = [$user->email, $user->role, $pilihan === null ? $password : '(sesuai --password)'];
        }

        foreach ($skipped as $email) {
            $this->components->info("Akun {$email} sudah bukan password bawaan, dilewati. Pakai --force untuk memaksa.");
        }

        if ($rotated === []) {
            $this->components->info('Semua akun sudah dirotasi sebelumnya. Tidak ada perubahan.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(['Email', 'Role', 'Password baru'], $rotated);

        if ($pilihan === null) {
            $this->components->warn('Password di atas hanya ditampilkan sekali dan tidak dicatat di log. Simpan sekarang.');
        } else {
            $this->components->info('Ketiganya memakai kata sandi yang kamu tentukan. Nilainya tidak ditayangkan ulang di sini, tetapi tertinggal di riwayat shell.');
        }

        return self::SUCCESS;
    }
}
