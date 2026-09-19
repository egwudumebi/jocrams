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
            ->assertJsonPath('data.fees.manuscript_review', 10000)
            ->assertJsonPath('data.journal.issn', '3156-2779')
            ->assertJsonPath('data.journal.editor_in_chief_message.signatory_name', 'Professor Patrick Ene Okon')
            ->assertJsonPath('data.payment_mode', 'bank_transfer');
    }
}
