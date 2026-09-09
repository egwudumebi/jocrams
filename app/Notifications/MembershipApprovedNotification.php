<?php

namespace App\Notifications;

use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Member $member) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Membership Approved')
            ->line('Your membership application has been approved.')
            ->line("Membership Number: {$this->member->membership_number}")
            ->action('Access Portal', url('/member'))
            ->line('Welcome aboard!');
    }
}
