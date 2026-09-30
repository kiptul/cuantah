<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureEmployee;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Mengikat sesi pada kata sandi yang sedang berlaku. Tanpa ini,
        // mengganti kata sandi tidak memutus sesi yang sudah terbuka di
        // perangkat lain, padahal justru itu alasan orang menggantinya.
        $middleware->web(append: [
            AuthenticateSession::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'employee' => EnsureEmployee::class,
        ]);

        // CATATAN: jangan aktifkan trustProxies selama aplikasi dilayani
        // langsung oleh web server (mis. shared hosting cPanel). Mempercayai
        // proxy sembarangan membuat $request->ip() diambil dari header
        // X-Forwarded-For yang bisa dipalsukan, sehingga throttle login pada
        // LoginRequest bisa dilewati. Aktifkan hanya bila benar-benar ada
        // proxy di depan, dan sebutkan IP proxy tersebut secara eksplisit:
        // $middleware->trustProxies(at: ['10.0.0.1']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
