<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

/**
 * Surel reset password dalam bahasa Indonesia.
 *
 * Bawaan Laravel berbahasa Inggris, dan ini satu-satunya surel yang pernah
 * dikirim aplikasi. Penerimanya rumah tangga dan pelaku UMKM, sehingga
 * surel berbahasa asing justru mudah dikira penipuan.
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $menitBerlaku = Config::get('auth.passwords.'.Config::get('auth.defaults.passwords').'.expire', 60);

        $tautan = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Atur Ulang Password CUANTAH')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Kami menerima permintaan untuk mengatur ulang password akun CUANTAH milikmu.')
            ->action('Atur Ulang Password', $tautan)
            ->line('Tautan ini hanya berlaku '.$menitBerlaku.' menit.')
            ->line('Bila kamu tidak merasa meminta, abaikan saja surel ini. Passwordmu tidak berubah.')
            ->salutation('Salam, Tim CUANTAH');
    }
}
