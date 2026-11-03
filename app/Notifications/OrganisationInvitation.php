<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrganisationInvitation extends Notification
{
    use Queueable;

    public function __construct(public string $url, public string $organisation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Invitation to Betterlink CyberAware AI')
            ->greeting('You have been invited')
            ->line("You have been invited to join {$this->organisation}.")
            ->action('Accept invitation', $this->url)
            ->line('This invitation expires in 48 hours. If you were not expecting it, you can ignore this message.');
    }
}
