<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Aplikasi berbahasa Indonesia, termasuk pesan galat dan surelnya.
 *
 * Tanpa berkas bahasa, pesan validasi keluar dalam bahasa Inggris — atau,
 * bila APP_FALLBACK_LOCALE ikut disetel id seperti pada .env.example,
 * keluar sebagai kunci mentah semacam "validation.required".
 */
class LocalisationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();
    }

    public function test_validation_messages_are_indonesian_even_when_the_fallback_is_also_id(): void
    {
        $this->app->setLocale('id');
        config(['app.fallback_locale' => 'id']);

        $this->post(route('register'), ['email' => 'bukan-email'])
            ->assertSessionHasErrors([
                'name' => 'Nama wajib diisi.',
                'email' => 'Email harus berupa alamat email yang sah.',
            ]);
    }

    public function test_field_names_in_messages_are_plain_indonesian_not_column_names(): void
    {
        $this->app->setLocale('id');
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->post(route('deposits.store'), [])
            ->assertSessionHasErrors(['estimated_liter' => 'Estimasi volume wajib diisi.']);
    }

    public function test_password_reset_email_is_sent_in_indonesian(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'penyetor@cuantah.test']);

        $this->post(route('password.email'), ['email' => 'penyetor@cuantah.test'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $surel = $notification->toMail($user);

            $this->assertSame('Atur Ulang Password CUANTAH', $surel->subject);
            $this->assertSame('Atur Ulang Password', $surel->actionText);
            $this->assertStringContainsString('mengatur ulang password', implode(' ', $surel->introLines));

            return true;
        });
    }

    public function test_reset_link_request_never_reveals_whether_the_email_exists(): void
    {
        Notification::fake();

        $adaAkun = $this->post(route('password.email'), ['email' => 'tidak.ada@cuantah.test']);

        $adaAkun->assertSessionHasNoErrors()->assertSessionHas('success');
        Notification::assertNothingSent();
    }
}
