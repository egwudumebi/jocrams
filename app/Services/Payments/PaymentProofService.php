<?php

namespace App\Services\Payments;

use App\Enums\PaymentGateway;
use App\Enums\PaymentProofStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\Visibility;
use App\Models\Donation;
use App\Models\EventRegistration;
use App\Models\JournalSubmission;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\ActivityLogService;
use App\Services\Membership\MediaUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofService
{
    public function __construct(
        private readonly MediaUploadService $mediaUpload,
        private readonly PaymentService $paymentService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function submit(
        User $user,
        PaymentPurpose $purpose,
        float $amount,
        UploadedFile $receipt,
        ?string $payerReference = null,
        ?string $memberNote = null,
        ?string $relatedUuid = null,
    ): PaymentProof {
        $member = Member::query()->where('user_id', $user->id)->first();
        $related = $this->resolveRelated($user, $purpose, $relatedUuid);

        if (in_array($purpose, [PaymentPurpose::Dues, PaymentPurpose::Registration], true) && ! $member) {
            throw ValidationException::withMessages([
                'purpose' => ['You need a membership profile before submitting this payment proof.'],
            ]);
        }

        return DB::transaction(function () use ($user, $member, $purpose, $amount, $receipt, $payerReference, $memberNote, $related) {
            $media = $this->mediaUpload->store(
                $receipt,
                'payment-proofs',
                $user,
                Visibility::Private,
                'local',
            );

            $proof = PaymentProof::query()->create([
                'user_id' => $user->id,
                'member_id' => $member?->id,
                'purpose' => $purpose,
                'amount' => $amount,
                'currency' => config('payments.currency', 'NGN'),
                'payer_reference' => $payerReference,
                'member_note' => $memberNote,
                'status' => PaymentProofStatus::Pending,
                'media_file_id' => $media->id,
                'related_type' => $related?->getMorphClass(),
                'related_id' => $related?->getKey(),
            ]);

            $media->update([
                'mediable_type' => $proof->getMorphClass(),
                'mediable_id' => $proof->id,
            ]);

            $this->activityLog->log($user, 'payments.proof_submitted', $proof, [
                'purpose' => $purpose->value,
                'amount' => (string) $amount,
            ]);

            return $proof->fresh(['mediaFile', 'member', 'related']);
        });
    }

    public function approve(PaymentProof $proof, User $admin): PaymentProof
    {
        if ($proof->status !== PaymentProofStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['Only pending payment proofs can be approved.'],
            ]);
        }

        return DB::transaction(function () use ($proof, $admin) {
            $proof->loadMissing(['user', 'member.tier', 'related']);
            $payable = $this->resolvePayableForApproval($proof);

            $payment = Payment::query()->create([
                'user_id' => $proof->user_id,
                'member_id' => $proof->member_id,
                'payable_type' => $payable?->getMorphClass(),
                'payable_id' => $payable?->getKey(),
                'gateway' => PaymentGateway::Manual,
                'reference' => 'PROOF_'.Str::upper(Str::random(14)),
                'idempotency_key' => 'proof_'.$proof->uuid,
                'amount' => $proof->amount,
                'currency' => $proof->currency,
                'status' => PaymentStatus::Pending,
                'purpose' => $proof->purpose,
                'description' => 'Bank transfer verified from payment proof '.$proof->uuid,
                'metadata' => [
                    'payment_method' => 'bank_transfer',
                    'payment_proof_uuid' => $proof->uuid,
                    'payer_reference' => $proof->payer_reference,
                    'manual_record' => true,
                    'approved_by' => $admin->id,
                ],
            ]);

            $this->paymentService->markSuccessful($payment, [
                'status' => 'successful',
                'amount' => (float) $proof->amount,
                'currency' => $proof->currency,
                'gateway_reference' => $proof->payer_reference ?: $payment->reference,
                'paid_at' => now()->toIso8601String(),
                'raw' => [
                    'payment_proof_uuid' => $proof->uuid,
                    'admin_id' => $admin->id,
                ],
            ]);

            $proof->update([
                'status' => PaymentProofStatus::Approved,
                'payment_id' => $payment->id,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->activityLog->log($admin, 'payments.proof_approved', $proof, [
                'payment_reference' => $payment->reference,
            ]);

            return $proof->fresh(['mediaFile', 'payment', 'user', 'member', 'reviewer', 'related']);
        });
    }

    public function reject(PaymentProof $proof, User $admin, string $reason): PaymentProof
    {
        if ($proof->status !== PaymentProofStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['Only pending payment proofs can be rejected.'],
            ]);
        }

        $proof->update([
            'status' => PaymentProofStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->activityLog->log($admin, 'payments.proof_rejected', $proof, [
            'reason' => $reason,
        ]);

        return $proof->fresh(['mediaFile', 'user', 'member', 'reviewer']);
    }

    public function download(PaymentProof $proof): StreamedResponse
    {
        $media = $proof->mediaFile;

        if (! $media || ! Storage::disk($media->disk)->exists($media->path)) {
            abort(404, 'Receipt file not found.');
        }

        return Storage::disk($media->disk)->download(
            $media->path,
            $media->original_filename ?: $media->filename,
        );
    }

    /** @return array<string, mixed> */
    public function present(PaymentProof $proof): array
    {
        $proof->loadMissing(['user', 'member', 'mediaFile', 'payment', 'reviewer', 'related']);

        return [
            'uuid' => $proof->uuid,
            'purpose' => $proof->purpose->value,
            'amount' => (float) $proof->amount,
            'currency' => $proof->currency,
            'payer_reference' => $proof->payer_reference,
            'member_note' => $proof->member_note,
            'status' => $proof->status->value,
            'rejection_reason' => $proof->rejection_reason,
            'created_at' => $proof->created_at?->toIso8601String(),
            'reviewed_at' => $proof->reviewed_at?->toIso8601String(),
            'user' => $proof->user ? [
                'uuid' => $proof->user->uuid,
                'name' => $proof->user->name,
                'email' => $proof->user->email,
            ] : null,
            'member' => $proof->member ? [
                'uuid' => $proof->member->uuid,
                'membership_number' => $proof->member->membership_number,
            ] : null,
            'reviewer' => $proof->reviewer ? [
                'name' => $proof->reviewer->name,
            ] : null,
            'payment' => $proof->payment ? [
                'uuid' => $proof->payment->uuid,
                'reference' => $proof->payment->reference,
                'status' => $proof->payment->status->value,
            ] : null,
            'receipt' => [
                'filename' => $proof->mediaFile?->original_filename,
                'mime_type' => $proof->mediaFile?->mime_type,
                'size' => $proof->mediaFile?->size,
            ],
            'related' => $this->presentRelated($proof),
        ];
    }

    private function resolveRelated(User $user, PaymentPurpose $purpose, ?string $relatedUuid): ?object
    {
        if (! $relatedUuid) {
            return null;
        }

        return match ($purpose) {
            PaymentPurpose::JournalSubmissionFee, PaymentPurpose::JournalPublicationFee => tap(
                JournalSubmission::query()->where('uuid', $relatedUuid)->first(),
                function (?JournalSubmission $submission) use ($user): void {
                    if (! $submission || $submission->author_id !== $user->uuid) {
                        throw ValidationException::withMessages([
                            'related_uuid' => ['Journal submission not found for this account.'],
                        ]);
                    }
                },
            ),
            PaymentPurpose::EventFee => tap(
                EventRegistration::query()->where('uuid', $relatedUuid)->first(),
                function (?EventRegistration $registration) use ($user): void {
                    if (! $registration || (int) $registration->user_id !== (int) $user->id) {
                        throw ValidationException::withMessages([
                            'related_uuid' => ['Event registration not found for this account.'],
                        ]);
                    }
                },
            ),
            default => null,
        };
    }

    private function resolvePayableForApproval(PaymentProof $proof): ?object
    {
        if ($proof->related instanceof JournalSubmission) {
            return $proof->related;
        }

        if ($proof->related instanceof EventRegistration) {
            return $proof->related;
        }

        $member = $proof->member?->loadMissing('tier', 'user');

        return match ($proof->purpose) {
            PaymentPurpose::Dues => $member ? Subscription::query()->create([
                'member_id' => $member->id,
                'membership_tier_id' => $member->membership_tier_id,
                'starts_at' => now(),
                'ends_at' => now()->addYear(),
                'status' => 'pending',
                'amount' => $proof->amount,
                'currency' => $member->tier?->currency ?: $proof->currency,
            ]) : null,
            PaymentPurpose::Donation => Donation::query()->create([
                'user_id' => $proof->user_id,
                'amount' => $proof->amount,
                'currency' => $proof->currency,
                'donor_name' => $proof->user?->name ?? 'Member',
                'donor_email' => $proof->user?->email ?? '',
            ]),
            default => null,
        };
    }

    /** @return array<string, mixed>|null */
    private function presentRelated(PaymentProof $proof): ?array
    {
        $related = $proof->related;

        if ($related instanceof JournalSubmission) {
            return [
                'type' => 'journal_submission',
                'uuid' => $related->uuid,
                'title' => $related->title,
            ];
        }

        if ($related instanceof EventRegistration) {
            return [
                'type' => 'event_registration',
                'uuid' => $related->uuid,
            ];
        }

        return null;
    }
}
