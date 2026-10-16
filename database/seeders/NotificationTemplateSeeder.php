<?php

namespace Database\Seeders;

use App\Enums\NotificationChannel;
use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'slug' => 'payment-receipt',
                'name' => 'Payment Receipt',
                'channel' => NotificationChannel::Email,
                'subject' => 'Payment Receipt — {{app_name}}',
                'body' => "Dear {{name}},\n\nThank you for your payment to {{app_name}}.\n\nAmount: {{currency}} {{amount}}\nReference: {{reference}}\nPurpose: {{purpose}}\nPaid on: {{paid_at}}\n\nYour official receipt is attached to this email as a PDF.\n\nRegards,\n{{app_name}}",
                'variables' => ['name', 'amount', 'currency', 'reference', 'purpose', 'paid_at', 'app_name'],
            ],
            [
                'slug' => 'membership-credential-pack',
                'name' => 'Membership Credential Pack',
                'channel' => NotificationChannel::Email,
                'subject' => 'Your {{association_name}} membership documents — {{app_name}}',
                'body' => "Dear {{name}},\n\nYour {{association_name}} membership has been confirmed. Your official membership number is {{member_number}} ({{tier_name}}), valid until {{expires_at}}.\n\nAttached to this email are your membership ID card, certificate, and welcome letter as PDF files. You can also access them anytime at {{credentials_url}}.\n\nAnyone can verify your documents at {{verify_url}} using the QR code on your ID card.\n\nWelcome to {{association_full_name}}.\n\nRegards,\n{{app_name}}",
                'variables' => [
                    'name',
                    'email',
                    'member_number',
                    'tier_name',
                    'expires_at',
                    'association_name',
                    'association_full_name',
                    'app_name',
                    'credentials_url',
                    'verify_url',
                ],
            ],
            [
                'slug' => 'renewal-reminder',
                'name' => 'Membership Renewal Reminder',
                'channel' => NotificationChannel::Email,
                'subject' => 'Your membership expires in {{days_remaining}} days',
                'body' => "Dear {{name}},\n\nYour {{tier_name}} membership ({{member_number}}) expires on {{expires_at}}.\nRenew now for {{renewal_amount}} NGN to maintain uninterrupted access.\n\nThank you,\n{{app_name}}",
                'variables' => ['name', 'tier_name', 'member_number', 'expires_at', 'days_remaining', 'renewal_amount', 'app_name'],
            ],
            [
                'slug' => 'event-confirmation',
                'name' => 'Event Registration Confirmation',
                'channel' => NotificationChannel::Email,
                'subject' => 'Event Registration Confirmed - {{event_title}}',
                'body' => "Dear {{name}},\n\nYou are registered for {{event_title}} on {{event_date}}.\nRegistration #: {{registration_number}}\nLocation: {{event_location}}\n\nSee you there!\n{{app_name}}",
                'variables' => ['name', 'event_title', 'event_date', 'event_location', 'registration_number', 'app_name'],
            ],
            [
                'slug' => 'renewal-reminder-sms',
                'name' => 'Renewal Reminder SMS',
                'channel' => NotificationChannel::Sms,
                'subject' => null,
                'body' => '{{app_name}}: Hi {{name}}, your membership expires {{expires_at}}. Renew at {{renewal_amount}} NGN.',
                'variables' => ['name', 'expires_at', 'renewal_amount', 'app_name'],
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::query()->updateOrCreate(
                ['slug' => $template['slug']],
                $template,
            );
        }
    }
}
