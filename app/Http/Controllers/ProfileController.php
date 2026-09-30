<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Pengaturan akun milik pengguna sendiri.
 *
 * Sebelumnya tidak ada jalan sama sekali: mengganti nomor telepon atau
 * kata sandi hanya mungkin lewat admin, dan tiga akun contoh tetap memakai
 * kata sandi bawaan karena pemiliknya tidak punya cara menggantinya.
 */
class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', ['user' => auth()->user()]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('success', 'Data akun berhasil diperbarui.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        // Sesi lain yang memakai kata sandi lama ikut diputus, sebab justru
        // sesi itulah yang biasanya menjadi alasan orang menggantinya.
        auth()->logoutOtherDevices($request->validated('current_password'));
        $request->session()->regenerate();

        return back()->with('success', 'Password berhasil diganti. Perangkat lain sudah dikeluarkan.');
    }
}
