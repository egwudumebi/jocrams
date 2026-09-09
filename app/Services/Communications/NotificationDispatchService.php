<?php

namespace App\Services\Communications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationLogStatus;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\TemplatedMailNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Illuminate\Notifications\AnonymousNotifiable;

class NotificationDispatchService
{
    public function __construct(private readonly TemplateRendererService $renderer) {}

    /** @param array<string, string> $variables */
    public function sendFromTemplate(
        NotificationTemplate $template,
        User $recipient,
        array $variables = [],
        ?Model $notifiable = null,
    ): NotificationLog {
        $rendered = $this->renderer->render($template, array_merge($this->defaultVariables($recipient), $variables));

        $log = NotificationLog::query()->create([
            'notification_template_id' => $template->id,
            'notifiable_type' => ($notifiable ?? $recipient)->getMorphClass(),
            'notifiable_id' => ($notifiable ?? $recipient)->getKey(),
            'channel' => $template->channel,
            'recipient' => $this->resolveRecipientAddress($recipient, $template->channel),
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'status' => NotificationLogStatus::Queued,
        ]);

        try {
            if ($template->channel === NotificationChannel::Email) {
                Notification::send($recipient, new TemplatedMailNotification(
                    $rendered['subject'] ?? $template->name,
                    $rendered['body'],
                ));
            } else {
                $this->sendSms($recipient, $rendered['body']);
            }

            $log->update(['status' => NotificationLogStatus::Sent, 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $log->update(['status' => NotificationLogStatus::Failed, 'error' => $e->getMessage()]);
        }

        return $log->fresh();
    }

    public function sendBySlug(string $slug, User $recipient, array $variables = [], ?Model $notifiable = null): ?NotificationLog
    {
        $template = NotificationTemplate::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return null;
        }

        return $this->sendFromTemplate($template, $recipient, $variables, $notifiable);
    }

    /** @param array<string, string> $variables */
    public function sendBySlugToEmail(
        string $slug,
        string $email,
        array $variables = [],
        ?Model $notifiable = null,
    ): ?NotificationLog {
        $template = NotificationTemplate::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $template || $template->channel !== NotificationChannel::Email || $email === '') {
            return null;
        }

        $rendered = $this->renderer->render($template, array_merge([
            'name' => $variables['name'] ?? 'Guest',
            'email' => $email,
            'app_name' => config('app.name'),
            'member_number' => '',
            'tier_name' => '',
            'expires_at' => '',
        ], $variables));

        $log = NotificationLog::query()->create([
            'notification_template_id' => $template->id,
            'notifiable_type' => $notifiable?->getMorphClass(),
            'notifiable_id' => $notifiable?->getKey(),
            'channel' => $template->channel,
            'recipient' => $email,
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'status' => NotificationLogStatus::Queued,
        ]);

        try {
            Notification::send(
                (new AnonymousNotifiable())->route('mail', $email),
                new TemplatedMailNotification(
                    $rendered['subject'] ?? $template->name,
                    $rendered['body'],
                ),
            );

            $log->update(['status' => NotificationLogStatus::Sent, 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $log->update(['status' => NotificationLogStatus::Failed, 'error' => $e->getMessage()]);
        }

        return $log->fresh();
    }

    /** @return array<string, string> */
    private function defaultVariables(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'app_name' => config('app.name'),
            'member_number' => $user->member?->membership_number ?? '',
            'tier_name' => $user->member?->tier?->name ?? '',
            'expires_at' => $user->member?->expires_at?->format('F j, Y') ?? '',
        ];
    }

    private function resolveRecipientAddress(User $user, NotificationChannel $channel): string
    {
        return match ($channel) {
            NotificationChannel::Email => $user->email,
            NotificationChannel::Sms => $user->phone ?? '',
        };
    }

    private function sendSms(User $user, string $body): void
    {
        $driver = config('services.sms.driver', 'log');

        if ($driver === 'log') {
            logger()->info('SMS sent', ['to' => $user->phone, 'body' => $body]);

            return;
        }

        // Placeholder for Termii / Africa's Talking integration
        throw new \RuntimeException('SMS driver not configured.');
    }
}
