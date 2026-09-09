<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Mail\PaymentReceiptMail;
use App\Models\Member;
use App\Models\MembershipTier;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentReceiptPdfService;
use App\Services\Payments\PaymentService;
use Database\Seeders\MembershipTierSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MembershipTierSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);
    }

    public function test_successful_payment_sends_branded_receipt_email_with_pdf_attachment(): void
    {
        Mail::fake();

        [$user, $payment] = $this->createMemberPayment(PaymentStatus::Processing);

        app(PaymentService::class)->markSuccessful($payment, [
            'gateway_reference' => 'GW-REF-001',
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => now()->toIso8601String(),
            'raw' => ['status' => 'success'],
        ]);

        Mail::assertQueued(PaymentReceiptMail::class, function (PaymentReceiptMail $mail) use ($user, $payment): bool {
            $this->assertSame($user->email, $mail->payment->user->email);
            $this->assertSame($payment->reference, $mail->payment->reference);

            $attachments = $mail->attachments();

            return count($attachments) === 1
                && str_contains($attachments[0]->as, 'receipt-'.$payment->reference.'.pdf');
        });
    }

    public function test_member_can_download_receipt_pdf_for_successful_payment(): void
    {
        [$user, $payment] = $this->createMemberPayment(PaymentStatus::Successful, paidAt: now());

        Sanctum::actingAs($user);

        $response = $this->get("/api/v1/member/payments/{$payment->uuid}/receipt");

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_member_cannot_download_receipt_for_pending_payment(): void
    {
        [$user, $payment] = $this->createMemberPayment(PaymentStatus::Pending);

        Sanctum::actingAs($user);

        $this->get("/api/v1/member/payments/{$payment->uuid}/receipt")
            ->assertNotFound();
    }

    public function test_receipt_pdf_service_renders_branded_context(): void
    {
        [$user, $payment] = $this->createMemberPayment(PaymentStatus::Successful, paidAt: now());

        $context = app(PaymentReceiptPdfService::class)->context($payment->fresh(['user', 'member.tier']));

        $this->assertSame($user->name, $context['payerName']);
        $this->assertSame($user->email, $context['payerEmail']);
        $this->assertSame('Annual membership dues', $context['purposeLabel']);
        $this->assertNotEmpty($context['siteName']);

        $pdfOutput = app(PaymentReceiptPdfService::class)->renderPdf($payment)->output();

        $this->assertNotEmpty($pdfOutput);
        $this->assertStringStartsWith('%PDF', $pdfOutput);
    }

    public function test_member_can_verify_dues_payment_after_paystack_redirect(): void
    {
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'id' => 123456,
                    'status' => 'success',
                    'reference' => 'PAYSTACK_VERIFY001',
                    'amount' => 5000000,
                    'currency' => 'NGN',
                    'paid_at' => now()->toIso8601String(),
                ],
            ], 200),
        ]);

        [$user, $payment] = $this->createMemberPayment(PaymentStatus::Processing);

        $payment->update(['reference' => 'PAYSTACK_VERIFY001']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/member/payments/verify/PAYSTACK_VERIFY001')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Successful->value);

        $this->assertDatabaseHas('payments', [
            'reference' => 'PAYSTACK_VERIFY001',
            'status' => PaymentStatus::Successful->value,
        ]);
    }

    /** @return array{0: User, 1: Payment} */
    private function createMemberPayment(PaymentStatus $status, ?\DateTimeInterface $paidAt = null): array
    {
        $user = User::factory()->create();
        $tier = MembershipTier::query()->first();

        $member = Member::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'membership_number' => 'MEM-RCPT001',
            'status' => MemberStatus::Active,
            'joined_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'member_id' => $member->id,
            'gateway' => PaymentGateway::Paystack,
            'reference' => 'PAYSTACK_RCPT001',
            'idempotency_key' => 'receipt-key-'.uniqid(),
            'amount' => 50000,
            'currency' => 'NGN',
            'status' => $status,
            'purpose' => PaymentPurpose::Dues,
            'description' => 'Annual membership dues',
            'paid_at' => $paidAt,
        ]);

        return [$user, $payment];
    }
}
