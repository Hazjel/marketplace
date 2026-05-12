<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    /**
     * Build the mail representation of the notification.
     * Override default agar link reset mengarah ke frontend (Vue), bukan backend.
     */
    public function toMail($notifiable): MailMessage
    {
        $frontendUrl = env("FRONTEND_URL", "http://localhost:5173");

        $url =
            $frontendUrl .
            "/auth/reset-password?token=" .
            $this->token .
            "&email=" .
            urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage())
            ->subject("Reset Password — Blukios")
            ->greeting("Halo, " . $notifiable->name . "!")
            ->line(
                "Kami menerima permintaan untuk mereset password akun Blukios kamu.",
            )
            ->line(
                "Klik tombol di bawah untuk membuat password baru. Link ini hanya berlaku selama **60 menit**.",
            )
            ->action("Reset Password Sekarang", $url)
            ->line(
                "Jika Anda tidak merasa meminta reset password, abaikan email ini. Akun Anda tetap aman.",
            )
            ->salutation("Salam hangat, Tim Blukios");
    }
}
