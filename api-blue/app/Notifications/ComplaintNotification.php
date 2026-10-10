<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A complaint changed status (see App\Support\ComplaintAlerts). Takes the
 * text, not the Complaint, so a queued mail says what was true when the
 * change happened.
 */
class ComplaintNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public readonly string $subject,
        public readonly array $lines,
        public readonly string $actionText,
        public readonly string $url,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->greeting('Halo, '.$notifiable->name.'!');

        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $mail->action($this->actionText, $this->url)->salutation('Salam hangat, Tim Blukios');
    }
}
