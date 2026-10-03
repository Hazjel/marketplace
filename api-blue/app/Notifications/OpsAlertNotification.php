<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent synchronously by `ops:check`. Deliberately not ShouldQueue: one of
 * the problems it reports is a dead queue worker.
 */
class OpsAlertNotification extends Notification
{
    /**
     * @param  list<array{title: string, lines: list<string>, cooldown: int}>  $problems  cooldown in minutes
     */
    public function __construct(public readonly array $problems) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->problems);
        $mail = (new MailMessage)
            ->error()
            ->subject("[Blukios] {$count} masalah operasional perlu dicek")
            ->greeting('Pemeriksaan otomatis menemukan masalah');

        foreach ($this->problems as $problem) {
            $mail->line('**'.$problem['title'].'**');
            foreach ($problem['lines'] as $line) {
                $mail->line($line);
            }
            // Each problem has its own cooldown (an hour for most, a day for backups and manual refunds).
            $minutes = $problem['cooldown'];
            $mail->line('_Tidak dikirim ulang sebelum '.($minutes % 60 === 0 && $minutes >= 120 ? ($minutes / 60).' jam' : $minutes.' menit').'._');
        }

        return $mail->salutation('ops:check, '.config('app.url'));
    }
}
