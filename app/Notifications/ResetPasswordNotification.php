<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Support\FrontendUrl;
use App\Support\Settings\SiteBranding;
use Illuminate\Support\Facades\DB;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your '.SiteBranding::siteName().' password')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We received a request to reset your account password.')
            ->action('Reset Password', $this->resetUrl($notifiable))
            ->line('This password reset link expires in '.config('auth.passwords.users.expire', 60).' minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }

    private function resetUrl(object $notifiable): string
    {
        $baseUrl = FrontendUrl::to($this->baseResetPath($notifiable));
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.http_build_query([
            'token' => $this->token,
            'email' => (string) $notifiable->email,
        ]);
    }

    private function baseResetPath(object $notifiable): string
    {
        if ($this->isAdmin($notifiable)) {
            return (string) config('auth.password_reset_urls.admin', '/admin/reset-password');
        }

        if ($this->isReviewer($notifiable)) {
            return (string) config('auth.password_reset_urls.reviewer', '/member/reset-password');
        }

        return (string) config('auth.password_reset_urls.member', '/member/reset-password');
    }

    private function isAdmin(object $notifiable): bool
    {
        return method_exists($notifiable, 'isAdmin') && $notifiable->isAdmin();
    }

    private function isReviewer(object $notifiable): bool
    {
        if (! isset($notifiable->id)) {
            return false;
        }

        return DB::table('roles')
            ->join('user_role', 'roles.id', '=', 'user_role.role_id')
            ->where('user_role.user_id', $notifiable->id)
            ->where('roles.slug', 'reviewer')
            ->exists();
    }
}
