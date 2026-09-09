<?php

namespace Tests\Feature\Admin;

use App\Enums\MemberStatus;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\JournalSubmission;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\MembershipTier;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
    }

    public function test_admin_can_view_reports_overview(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $tier = MembershipTier::query()->first();
        Member::query()->create([
            'user_id' => User::factory()->create()->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-REPORT01',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        MembershipApplication::query()->create([
            'user_id' => User::factory()->create()->id,
            'membership_tier_id' => $tier->id,
            'applicant_name' => 'Pending Applicant',
            'applicant_email' => 'pending@example.com',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        JournalSubmission::query()->create([
            'author_id' => User::factory()->create()->uuid,
            'title' => 'Pending Article',
            'slug' => 'pending-article',
            'author_name' => 'Author',
            'author_email' => 'author@example.com',
            'abstract' => 'Abstract',
            'category' => 'Research',
            'document_path' => 'journal/pending.pdf',
            'status' => 'submitted',
        ]);

        Payment::query()->create([
            'user_id' => User::factory()->create()->id,
            'reference' => 'PAY-REPORT-001',
            'idempotency_key' => 'idem-report-001',
            'amount' => 5000,
            'currency' => 'NGN',
            'status' => 'successful',
            'purpose' => 'dues',
            'gateway' => 'paystack',
            'paid_at' => now(),
        ]);

        Event::query()->create([
            'organizer_id' => User::query()->where('email', 'admin@jocrams.test')->value('id'),
            'title' => 'Annual Meeting',
            'slug' => 'annual-meeting',
            'starts_at' => now()->addMonth(),
            'status' => 'published',
        ]);

        $response = $this->getJson('/api/v1/admin/reports/overview');

        $response->assertOk()
            ->assertJsonPath('data.members_active', 1)
            ->assertJsonPath('data.applications_pending', 1)
            ->assertJsonPath('data.journal_submissions_pending', 1)
            ->assertJsonPath('data.events_total', 1)
            ->assertJsonStructure([
                'data' => [
                    'members_total',
                    'members_active',
                    'applications_pending',
                    'applications_growth_trend_6_months',
                    'journal_submissions_pending',
                    'payments_total',
                    'events_total',
                    'support_open',
                ],
            ]);
    }

    public function test_admin_can_view_recent_activities(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $admin = User::query()->where('email', 'admin@jocrams.test')->first();

        ActivityLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'payment.marked_successful',
            'properties' => ['reference' => 'PAY-001'],
        ]);

        $response = $this->getJson('/api/v1/admin/reports/recent-activities?limit=10');

        $response->assertOk()
            ->assertJsonFragment(['action' => 'payment.marked_successful'])
            ->assertJsonFragment(['actor_email' => 'admin@jocrams.test']);
    }

    public function test_member_can_view_dashboard_snapshot(): void
    {
        $user = User::factory()->create();
        $tier = MembershipTier::query()->first();

        Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-DASH01',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/member/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.membership.membership_number', 'MEM-DASH01')
            ->assertJsonStructure([
                'data' => [
                    'membership',
                    'events_attended',
                    'upcoming_events',
                    'journal_submissions_count',
                    'journal_submissions',
                    'credentials_count',
                ],
            ]);
    }
}
