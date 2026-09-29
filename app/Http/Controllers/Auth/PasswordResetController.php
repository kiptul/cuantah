<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\NewPasswordRequest;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showLinkRequest(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Kirim tautan reset ke email pengguna.
     *
     * Status selalu dilaporkan sama apa pun hasilnya, supaya halaman ini
     * tidak bisa dipakai untuk menebak email mana yang terdaftar.
     */
    public function sendLink(PasswordResetLinkRequest $request): RedirectResponse
    {
        Password::sendResetLink($request->validated());

        return back()->with('success', 'Kalau email tersebut terdaftar, tautan reset password sudah kami kirim. Cek kotak masuk dan folder spam.');
    }

    public function showReset(string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request()->string('email')->toString(),
        ]);
    }

    public function update(NewPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->validated(),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('success', 'Password berhasil diperbarui. Silakan login.');
    }
}
