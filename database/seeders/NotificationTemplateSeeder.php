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
