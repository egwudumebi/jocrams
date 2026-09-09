<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Mail\OnboardingOtpMail;
use App\Models\MembershipApplication;
use App\Models\MembershipTier;
use App\Models\User;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        Storage::fake('local');
        Mail::fake();
    }

    public function test_start_sends_otp_email(): void
    {
        $response = $this->postJson('/api/v1/public/onboarding/start', [
            'email' => 'applicant@example.com',
            'phone' => '+2348012345678',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ]);

        $response->assertCreated()
            ->assertJsonPath('state', 'personal_info_submitted')
            ->assertJsonStructure(['session_id', 'otp_debug_code']);

        Mail::assertSent(OnboardingOtpMail::class);
    }

    public function test_start_rejects_existing_account_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->postJson('/api/v1/public/onboarding/start', [
            'email' => 'existing@example.com',
            'phone' => '+2348012345678',
            'first_name' => 'Existing',
            'last_name' => 'User',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_verify_rejects_invalid_otp(): void
    {
        $start = $this->postJson('/api/v1/public/onboarding/start', [
            'email' => 'verify@example.com',
            'phone' => '+2348012345678',
            'first_name' => 'Verify',
            'last_name' => 'User',
        ])->assertCreated();

        $this->postJson('/api/v1/public/onboarding/verify-email', [
            'session_id' => $start->json('session_id'),
            'otp' => '000000',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['otp']);
    }

    public function test_professional_details_require_verified_email(): void
    {
        $tier = MembershipTier::query()->first();

        $start = $this->postJson('/api/v1/public/onboarding/start', [
            'email' => 'blocked@example.com',
            'phone' => '+2348012345678',
            'first_name' => 'Blocked',
            'last_name' => 'User',
        ])->assertCreated();

        $this->post('/api/v1/public/onboarding/professional-details', [
            'session_id' => $start->json('session_id'),
            'membership_tier_id' => $tier->id,
            'institution' => 'University',
            'qualification' => 'BSc',
            'years_experience' => 3,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['session_id']);
    }

    public function test_full_onboarding_creates_submitted_application(): void
    {
        Notification::fake();

        $tier = MembershipTier::query()->first();

        $start = $this->postJson('/api/v1/public/onboarding/start', [
            'email' => 'newmember@example.com',
            'phone' => '+2348098765432',
            'first_name' => 'New',
            'last_name' => 'Member',
        ])->assertCreated();

        $sessionId = $start->json('session_id');
        $otp = $start->json('otp_debug_code');

        $this->postJson('/api/v1/public/onboarding/verify-email', [
            'session_id' => $sessionId,
            'otp' => $otp,
        ])->assertOk()
            ->assertJsonPath('state', 'email_verified');

        $this->post('/api/v1/public/onboarding/professional-details', [
            'session_id' => $sessionId,
            'membership_tier_id' => $tier->id,
            'institution' => 'Tech Institute',
            'qualification' => 'MSc',
            'years_experience' => 5,
            'supporting_documents' => [
                UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
            ],
        ])->assertOk()
            ->assertJsonPath('state', 'professional_details_completed');

        $submit = $this->postJson('/api/v1/public/onboarding/submit', [
            'session_id' => $sessionId,
        ])->assertOk()
            ->assertJsonPath('state', 'application_submitted')
            ->assertJsonPath('password_reset_sent', true);

        $this->assertDatabaseHas('users', [
            'email' => 'newmember@example.com',
            'has_changed_password' => 0,
        ]);

        $this->assertDatabaseHas('membership_applications', [
            'applicant_email' => 'newmember@example.com',
            'status' => ApplicationStatus::UnderReview->value,
        ]);

        $application = MembershipApplication::query()
            ->where('uuid', $submit->json('application_uuid'))
            ->first();

        $this->assertNotNull($application);
        $this->assertSame('newmember@example.com', $application->applicant_email);
    }
}
