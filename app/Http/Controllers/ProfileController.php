<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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
        return view('profile.edit', ['user' => Auth::user()]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('success', 'Data akun berhasil diperbarui.');
    }

    /**
     * Mengganti kata sandi sendiri.
     *
     * Sesi di perangkat lain ikut terputus dengan sendirinya: middleware
     * AuthenticateSession menyimpan sidik kata sandi di dalam sesi dan
     * membandingkannya pada tiap permintaan, lalu menyegarkan sidik untuk
     * sesi yang sedang berjalan sesudah respons dikirim. Memanggil
     * logoutOtherDevices() di sini justru keliru — pemeriksaannya
     * membandingkan kata sandi lama dengan yang sudah berganti, sehingga
     * selalu gagal.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        $request->session()->regenerate();

        return back()->with('success', 'Password berhasil diganti. Sesi di perangkat lain akan keluar dengan sendirinya.');
    }
}
