<?php

namespace App\Notifications;

use App\Support\FrontendUrl;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends VerifyEmail
{
    public function __construct(private readonly string $context = 'member') {}

    protected function verificationUrl($notifiable): string
    {
        $id = $notifiable->getKey();
        $hash = sha1($notifiable->getEmailForVerification());

        $routeName = $this->context === 'admin'
            ? 'verification.admin.verify'
            : 'verification.member.verify';

        $frontendKey = $this->context === 'admin'
            ? 'auth.verification_urls.admin'
            : 'auth.verification_urls.member';

        $signedApiUrl = URL::temporarySignedRoute(
            $routeName,
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            ['id' => $id, 'hash' => $hash],
        );

        parse_str(parse_url($signedApiUrl, PHP_URL_QUERY) ?: '', $query);

        $frontendBase = FrontendUrl::to((string) config($frontendKey, '/member/verify-email'));

        return $frontendBase.'?'.http_build_query(array_merge($query, [
            'id' => $id,
            'hash' => $hash,
        ]));
    }
}
