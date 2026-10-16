<?php

namespace App\Services\Credentials;

use App\Enums\CredentialType;
use App\Enums\NotificationChannel;
use App\Enums\NotificationLogStatus;
use App\Mail\MembershipCredentialPackMail;
use App\Models\DigitalCredential;
use App\Models\Member;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Services\Communications\TemplateRendererService;
use App\Support\Settings\SiteBranding;
use Illuminate\Support\Facades\Mail;

class MembershipCredentialPackDeliveryService
{
    public function __construct(
        private readonly TemplateRendererService $renderer,
    ) {}

    /**
     * @param  list<DigitalCredential>  $issuedCredentials
     */
    public function send(Member $member, array $issuedCredentials, string $organization = 'sicama'): void
    {
        $member->loadMissing(['user.profile', 'tier']);

        $email = trim((string) ($member->user?->email ?? ''));

        if ($email === '' || str_contains($email, '@members.jocrams.local')) {
            return;
        }

        $credentials = $this->orderedPackCredentials($issuedCredentials);

        if ($credentials === []) {
            return;
        }

        $name = trim((string) ($member->user?->name ?? 'Member'));
        $associationName = $organization === 'sec'
            ? (SiteBranding::parentOrgName() ?: 'SEC')
            : (SiteBranding::parentOrgName() ?: 'SICAMA');

        [$subject, $body] = $this->resolveEmailContent($member, $name, $associationName);

        try {
            Mail::to($email)->send(new MembershipCredentialPackMail(
                member: $member,
                credentials: $credentials,
                recipientName: $name,
                associationName: $associationName,
                emailBody: $body,
                emailSubject: $subject,
            ));

            $this->logDelivery($member, $email, $subject, $body, NotificationLogStatus::Sent);
        } catch (\Throwable $exception) {
            $this->logDelivery($member, $email, $subject, $body, NotificationLogStatus::Failed, $exception->getMessage());

            throw $exception;
        }
    }

    /**
     * @param  list<DigitalCredential>  $issuedCredentials
     * @return list<DigitalCredential>
     */
    private function orderedPackCredentials(array $issuedCredentials): array
    {
        $order = [
            CredentialType::MembershipCard->value => 0,
            CredentialType::Certificate->value => 1,
            CredentialType::WelcomeLetter->value => 2,
        ];

        $withFiles = [];

        foreach ($issuedCredentials as $credential) {
            $credential->loadMissing('file');

            if ($credential->file) {
                $withFiles[] = $credential;
            }
        }

        usort($withFiles, fn (DigitalCredential $a, DigitalCredential $b): int => (
            ($order[$a->type->value] ?? 9) <=> ($order[$b->type->value] ?? 9)
        ));

        return $withFiles;
    }

    /** @return array{0: string, 1: string|null} */
    private function resolveEmailContent(Member $member, string $recipientName, string $associationName): array
    {
        $template = NotificationTemplate::query()
            ->where('slug', 'membership-credential-pack')
            ->where('is_active', true)
            ->first();

        $defaultSubject = 'Your '.$associationName.' membership documents — '.SiteBranding::siteName();

        if (! $template || $template->channel !== NotificationChannel::Email) {
            return [$defaultSubject, null];
        }

        $variables = [
            'name' => $recipientName,
            'email' => $member->user?->email ?? '',
            'member_number' => $member->membership_number,
            'tier_name' => $member->tier?->name ?? 'Member',
            'expires_at' => $member->expires_at?->format('F j, Y') ?? '',
            'association_name' => $associationName,
            'association_full_name' => SiteBranding::parentOrgFullName() ?: $associationName,
            'app_name' => SiteBranding::siteName(),
            'credentials_url' => url('/member/credentials'),
            'verify_url' => url('/verify'),
        ];

        $rendered = $this->renderer->render($template, $variables);

        return [
            $rendered['subject'] ?? $defaultSubject,
            $rendered['body'] ?? null,
        ];
    }

    private function logDelivery(
        Member $member,
        string $email,
        string $subject,
        ?string $body,
        NotificationLogStatus $status,
        ?string $error = null,
    ): void {
        NotificationLog::query()->create([
            'notification_template_id' => NotificationTemplate::query()
                ->where('slug', 'membership-credential-pack')
                ->value('id'),
            'notifiable_type' => $member->getMorphClass(),
            'notifiable_id' => $member->getKey(),
            'channel' => NotificationChannel::Email,
            'recipient' => $email,
            'subject' => $subject,
            'body' => $body ?? 'Membership credential pack email with PDF attachments.',
            'status' => $status,
            'sent_at' => $status === NotificationLogStatus::Sent ? now() : null,
            'error' => $error,
        ]);
    }
}
