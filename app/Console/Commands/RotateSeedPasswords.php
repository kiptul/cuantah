<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('cuantah:rotate-seed-passwords {--email=* : Batasi rotasi pada alamat email tertentu} {--force : Rotasi juga akun yang passwordnya sudah bukan bawaan}')]
#[Description('Mengganti password akun seed dengan password acak dan menampilkannya satu kali')]
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

    private const DEFAULT_PASSWORD = 'password';

    private const PASSWORD_LENGTH = 20;

    public function handle(): int
    {
        $emails = $this->option('email') ?: self::SEED_EMAILS;
        $force = (bool) $this->option('force');

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

            $password = Str::password(self::PASSWORD_LENGTH, symbols: false);

            /**
             * Remember token ikut diputar: tanpa itu cookie "ingat saya" yang
             * dibuat dengan password bawaan tetap sah, jadi rotasi password
             * saja belum mengeluarkan siapa pun yang sudah masuk.
             */
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            $rotated[] = [$user->email, $user->role, $password];
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
        $this->components->warn('Password di atas hanya ditampilkan sekali dan tidak dicatat di log. Simpan sekarang.');

        return self::SUCCESS;
    }
}
