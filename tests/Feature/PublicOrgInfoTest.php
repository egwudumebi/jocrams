<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicOrgInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_org_info_includes_uba_bank_and_journal_content(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $response = $this->getJson('/api/v1/public/org-info');

        $response->assertOk()
            ->assertJsonPath('data.bank.account_number', '1030789503')
            ->assertJsonPath('data.bank.bank_name', 'United Bank for Africa (UBA)')
            ->assertJsonPath('data.bank.account_name', 'Scholars in Communication and Media Advancement Initiative (SICAMA Initiative)')
            ->assertJsonPath('data.fees.manuscript_review', 10000)
            ->assertJsonPath('data.journal.issn', '3156-2779')
            ->assertJsonPath('data.journal.editor_in_chief_message.signatory_name', 'Professor Patrick Ene Okon')
            ->assertJsonPath('data.payment_mode', 'bank_transfer');
    }

    public function test_placeholder_bank_settings_are_ignored_for_public_org_info(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        \App\Models\SystemSetting::query()->updateOrCreate(
            ['group_name' => 'payment', 'setting_key' => 'bank_name'],
            ['setting_value' => 'First Bank Nigeria', 'is_secret' => false],
        );
        \App\Models\SystemSetting::query()->updateOrCreate(
            ['group_name' => 'payment', 'setting_key' => 'account_name'],
            ['setting_value' => 'Jocrams Association', 'is_secret' => false],
        );
        \App\Models\SystemSetting::query()->updateOrCreate(
            ['group_name' => 'payment', 'setting_key' => 'account_number'],
            ['setting_value' => '1234567890', 'is_secret' => false],
        );

        $this->getJson('/api/v1/public/org-info')
            ->assertOk()
            ->assertJsonPath('data.bank.account_number', '1030789503')
            ->assertJsonPath('data.bank.bank_name', 'United Bank for Africa (UBA)')
            ->assertJsonPath('data.bank.account_name', 'Scholars in Communication and Media Advancement Initiative (SICAMA Initiative)');
    }
}
