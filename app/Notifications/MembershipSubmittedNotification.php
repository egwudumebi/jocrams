<?php

namespace App\Notifications;

use App\Models\MembershipApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly MembershipApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Application Submitted')
            ->line('Your membership application has been submitted for review.')
            ->line('We will notify you once a decision is made.');
    }
}
