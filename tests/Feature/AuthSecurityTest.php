<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();

        RateLimiter::clear('login');
    }

    public function test_login_is_locked_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'korban@cuantah.test',
            'password' => Hash::make('password-benar'),
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'password-salah',
            ])->assertSessionHasErrors('email');
        }

        // Percobaan keenam ditolak oleh throttle, bukan oleh pengecekan password.
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password-benar',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Terlalu banyak percobaan login',
            session('errors')->first('email')
        );
        $this->assertGuest();
    }

    public function test_successful_login_clears_the_rate_limiter(): void
    {
        $user = User::factory()->create([
            'email' => 'user@cuantah.test',
            'password' => Hash::make('password-benar'),
            'role' => 'user',
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->post('/login', ['email' => $user->email, 'password' => 'password-benar']);
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');

        // Hitungan gagal sebelumnya sudah direset, jadi masih ada jatah penuh.
        $this->post('/login', ['email' => $user->email, 'password' => 'salah'])
            ->assertSessionHasErrors('email');
        $this->assertStringNotContainsString(
            'Terlalu banyak percobaan login',
            session('errors')->first('email')
        );
    }

    public function test_user_can_reset_password_through_emailed_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'lupa@cuantah.test']);

        $this->post('/lupa-password', ['email' => $user->email])
            ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password-baru',
                'password_confirmation' => 'password-baru',
            ])->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('password-baru', $user->fresh()->password));
    }

    public function test_reset_request_does_not_reveal_whether_email_is_registered(): void
    {
        Notification::fake();

        $terdaftar = User::factory()->create(['email' => 'ada@cuantah.test']);

        $responseAda = $this->post('/lupa-password', ['email' => $terdaftar->email]);
        $responseTidakAda = $this->post('/lupa-password', ['email' => 'tidak-ada@cuantah.test']);

        $this->assertSame(
            $responseAda->getSession()->get('success'),
            $responseTidakAda->getSession()->get('success'),
            'Pesan untuk email terdaftar dan tidak terdaftar harus identik.'
        );

        Notification::assertSentTo($terdaftar, ResetPassword::class);
        Notification::assertSentTimes(ResetPassword::class, 1);
    }
}
